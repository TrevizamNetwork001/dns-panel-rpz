<?php

namespace App\Services\Rbl;

use App\Models\RblList;
use App\Models\RblTarget;
use App\Models\RblTargetScanState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RblChecker
{
    public function __construct(private DnsblResolver $resolver, private RblEventService $events) {}

    public function check(RblTarget $target, ?int $runId = null): RblTarget
    {
        // A shared lock also bounds overall synchronous DNS volume across administrators.
        // DNS (20s) plus up to ten Telegram transitions (5s each), with storage margin.
        $lock = Cache::lock('rbl:manual-check', 120);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['check' => 'Há uma verificação em andamento. Aguarde e tente novamente.']);
        }
        try {
            $target->refresh();
            if (! $target->enabled || ($target->group && ! $target->group->enabled)) {
                throw ValidationException::withMessages(['check' => 'Este alvo está desativado.']);
            }
            if ($target->last_checked_at?->gt(now()->subMinute())) {
                throw ValidationException::withMessages(['check' => 'Aguarde um minuto entre verificações do mesmo alvo.']);
            }
            $lists = RblList::where('enabled', true)->orderBy('id')->get();
            if ($lists->isEmpty()) {
                throw ValidationException::withMessages(['check' => 'Ative pelo menos uma lista RBL.']);
            }
            $maxChecks = max(1, min(1000, (int) config('rbl.max_checks_per_target', 40)));
            $maxSeconds = max(1, min(120, (int) config('rbl.max_seconds_per_target', 20)));
            $deadline = microtime(true) + $maxSeconds;
            $results = [];
            $queries = 0;
            $ipListCount = max(1, $lists->where('type', 'ip')->count());
            $largeBatchLimit = min((int) config('rbl.batch_ips_per_run', 8), max(1, intdiv($maxChecks, $ipListCount)));
            $plan = app(TargetExpansion::class)->plan($target, $largeBatchLimit);
            foreach ($lists as $list) {
                foreach ($plan['ips'] ?: [$target->value] as $ip) {
                    $query = null;
                    $result = ['status' => 'skipped', 'error_message' => $plan['reason'] ?? 'Lista incompatível: requer tipo IP.'];
                    if ($plan['ips'] && $list->type === 'ip') {
                        if ($queries >= $maxChecks || microtime(true) >= $deadline) {
                            $result = ['status' => 'skipped', 'error_message' => "Limite de {$maxChecks} consultas ou {$maxSeconds} segundos atingido."];
                        } else {
                            try {
                                $query = $this->resolver->queryFor($ip, $list->dns_zone);
                                $queries++;
                                $result = $this->resolver->resolve($query, min(max(1, $list->timeout_seconds), 5, max(0.001, $deadline - microtime(true))));
                            } catch (Throwable) {
                                $result = ['status' => 'error', 'error_message' => 'Falha controlada ao consultar a lista DNSBL.'];
                            }
                        }
                    }
                    $results[] = array_merge($result, [
                        'rbl_run_id' => $runId, 'rbl_list_id' => $list->id, 'checked_value' => $ip,
                        'query' => $query, 'checked_at' => now(),
                    ]);
                }
            }
            DB::transaction(function () use ($target, $results, $plan) {
                RblTarget::whereKey($target->id)->lockForUpdate()->firstOrFail();
                foreach ($results as $result) {
                    $check = $target->checks()->create($result);
                    $this->events->record($check);
                }
                $statuses = array_column($results, 'status');
                $status = in_array('listed', $statuses, true) ? 'listed'
                    : (array_intersect(['error', 'timeout'], $statuses) ? 'error'
                    : (in_array('skipped', $statuses, true) ? ($target->type === 'cidr' ? 'skipped' : 'unchecked') : 'clean'));
                if ($target->type === 'cidr' && $plan['reason'] === null) {
                    $state = RblTargetScanState::where('rbl_target_id', $target->id)->lockForUpdate()->first();
                    $newCycle = ! $state || ($plan['cursor'] === 0 && $state->completed_at);
                    $summary = $newCycle ? [] : ($state->summary ?? []);
                    foreach (collect($results)->groupBy('checked_value') as $ip => $ipResults) {
                        $ipStatuses = $ipResults->pluck('status')->all();
                        $summary[$ip] = in_array('listed', $ipStatuses, true) ? 'listed'
                            : (array_intersect(['error', 'timeout'], $ipStatuses) ? 'error'
                            : (in_array('skipped', $ipStatuses, true) ? 'skipped' : 'clean'));
                    }
                    $counts = array_count_values($summary);
                    $state = RblTargetScanState::updateOrCreate(['rbl_target_id' => $target->id], [
                        'cursor' => $plan['next_cursor'], 'cycle' => $newCycle ? (($state?->cycle ?? 0) + 1) : $state->cycle,
                        'total_ips' => $plan['total_ips'], 'scanned_ips' => count($summary),
                        'listed_ips' => $counts['listed'] ?? 0, 'clean_ips' => $counts['clean'] ?? 0,
                        'skipped_ips' => $counts['skipped'] ?? 0, 'error_ips' => $counts['error'] ?? 0,
                        'started_at' => $newCycle ? now() : $state->started_at,
                        'completed_at' => $plan['cycle_complete'] ? now() : null,
                        'last_checked_at' => now(), 'summary' => $summary,
                    ]);
                    $hasOpenEvent = $target->events()->where('status', 'open')->exists();
                    $status = $hasOpenEvent ? 'listed' : ($plan['cycle_complete']
                        ? (($state->error_ips > 0) ? 'error' : (($state->skipped_ips > 0) ? 'skipped' : 'clean'))
                        : 'partial');
                }
                $target->update(['last_status' => $status, 'last_checked_at' => now()]);
            });

            return $target->refresh();
        } finally {
            $lock->release();
        }
    }
}
