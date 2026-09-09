<?php

namespace App\Services\Rbl;

use App\Models\RblList;
use App\Models\RblTarget;
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
        $lock = Cache::lock('rbl:manual-check', 60);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['check' => 'Há uma verificação em andamento. Aguarde e tente novamente.']);
        }
        try {
            $target->refresh();
            if (! $target->enabled) {
                throw ValidationException::withMessages(['check' => 'Este alvo está desativado.']);
            }
            if ($target->last_checked_at?->gt(now()->subMinute())) {
                throw ValidationException::withMessages(['check' => 'Aguarde um minuto entre verificações do mesmo alvo.']);
            }
            $lists = RblList::where('enabled', true)->orderBy('id')->get();
            if ($lists->isEmpty()) {
                throw ValidationException::withMessages(['check' => 'Ative pelo menos uma lista RBL.']);
            }
            $deadline = microtime(true) + 20;
            $results = [];
            $queries = 0;
            foreach ($lists as $list) {
                $query = null;
                $result = ['status' => 'skipped', 'error_message' => 'Consulta disponível apenas para IPv4 individual em listas do tipo IP.'];
                if ($target->type === 'ip' && filter_var($target->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $list->type === 'ip') {
                    if ($queries >= 10 || microtime(true) >= $deadline) {
                        $result = ['status' => 'skipped', 'error_message' => 'Limite de 10 consultas ou 20 segundos atingido.'];
                    } else {
                        try {
                            $query = $this->resolver->queryFor($target->value, $list->dns_zone);
                            $queries++;
                            $result = $this->resolver->resolve($query, min(max(1, $list->timeout_seconds), 5, max(0.001, $deadline - microtime(true))));
                        } catch (Throwable) {
                            $result = ['status' => 'error', 'error_message' => 'Falha controlada ao consultar a lista DNSBL.'];
                        }
                    }
                }
                $results[] = array_merge($result, [
                    'rbl_run_id' => $runId, 'rbl_list_id' => $list->id, 'checked_value' => $target->value,
                    'query' => $query, 'checked_at' => now(),
                ]);
            }
            DB::transaction(function () use ($target, $results) {
                RblTarget::whereKey($target->id)->lockForUpdate()->firstOrFail();
                foreach ($results as $result) {
                    $check = $target->checks()->create($result);
                    $this->events->record($check);
                }
                $statuses = array_column($results, 'status');
                $status = in_array('listed', $statuses, true) ? 'listed'
                    : (array_intersect(['error', 'timeout'], $statuses) ? 'error'
                    : (in_array('skipped', $statuses, true) ? 'unchecked' : 'clean'));
                $target->update(['last_status' => $status, 'last_checked_at' => now()]);
            });

            return $target->refresh();
        } finally {
            $lock->release();
        }
    }
}
