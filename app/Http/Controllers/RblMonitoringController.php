<?php

namespace App\Http\Controllers;

use App\Models\RblAlert;
use App\Models\RblCheck;
use App\Models\RblEvent;
use App\Models\RblList;
use App\Models\RblTarget;
use App\Models\RblTargetGroup;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RblMonitoringController extends Controller
{
    private function period(Request $request): array
    {
        $request->merge([
            'start' => $request->input('start', now()->subDays(29)->toDateString()),
            'end' => $request->input('end', now()->toDateString()),
        ]);
        $data = $request->validate([
            'group' => ['nullable', 'integer', 'exists:rbl_target_groups,id'],
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
        ]);

        return [CarbonImmutable::parse($data['start'])->startOfDay(), CarbonImmutable::parse($data['end'])->addDay()->startOfDay()];
    }

    public function events(Request $request)
    {
        [$start, $until] = $this->period($request);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'resolved', 'all'])],
            'target' => ['nullable', 'integer', 'exists:rbl_targets,id'],
            'list' => ['nullable', 'integer', 'exists:rbl_lists,id'],
        ]);
        $status = $filters['status'] ?? 'all';
        // Events active at any point in the interval, including older open events.
        $events = RblEvent::with(['target.group', 'list', 'alerts'])->when($request->input('group'), fn ($q, $id) => $q->whereHas('target', fn ($t) => $t->where('rbl_target_group_id', $id)))->where('first_seen_at', '<', $until)
            ->where(fn ($q) => $q->whereNull('resolved_at')->orWhere('resolved_at', '>=', $start))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($filters['target'] ?? null, fn ($q, $id) => $q->where('rbl_target_id', $id))
            ->when($filters['list'] ?? null, fn ($q, $id) => $q->where('rbl_list_id', $id))
            ->latest('last_seen_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('rbl.events', [
            'groups' => RblTargetGroup::orderBy('name')->get(), 'events' => $events, 'targets' => RblTarget::orderBy('name')->get(),
            'lists' => RblList::orderBy('name')->get(), 'status' => $status,
        ]);
    }

    public function reports(Request $request)
    {
        [$start, $until] = $this->period($request);
        $request->validate(['format' => ['nullable', Rule::in(['csv'])]]);
        $alerts = RblAlert::where('created_at', '>=', $start)->where('created_at', '<', $until)
            ->when($request->input('group'), fn ($q, $id) => $q->whereHas('event.target', fn ($t) => $t->where('rbl_target_group_id', $id)));
        $alertSummary = [
            'Alertas enviados' => (clone $alerts)->where('status', 'sent')->count(),
            'Alertas falhos' => (clone $alerts)->where('status', 'failed')->count(),
            'Eventos listed com alerta enviado' => (clone $alerts)->where('status', 'sent')->where('type', 'listed')->count(),
            'Eventos resolved com alerta enviado' => (clone $alerts)->where('status', 'sent')->where('type', 'resolved')->count(),
        ];
        $groups = RblTargetGroup::orderBy('name')->get();
        $checks = RblCheck::where('checked_at', '>=', $start)->where('checked_at', '<', $until)
            ->when($request->input('group'), fn ($q, $id) => $q->whereHas('target', fn ($t) => $t->where('rbl_target_group_id', $id)));
        $events = RblEvent::when($request->input('group'), fn ($q, $id) => $q->whereHas('target', fn ($t) => $t->where('rbl_target_group_id', $id)));
        $groupSummary = [];
        foreach ($groups->when($request->input('group'), fn ($g, $id) => $g->where('id', $id)) as $group) {
            $groupChecks = (clone $checks)->whereHas('target', fn ($q) => $q->where('rbl_target_group_id', $group->id));
            $c = $groupChecks->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
            $e = (clone $events)->whereHas('target', fn ($q) => $q->where('rbl_target_group_id', $group->id));
            $groupSummary[] = [
                'Grupo' => $group->name, 'Checks' => $c->sum(), 'Listed' => $c['listed'] ?? 0,
                'Clean' => $c['clean'] ?? 0, 'Skipped' => $c['skipped'] ?? 0,
                'Errors' => ($c['error'] ?? 0) + ($c['timeout'] ?? 0),
                'Eventos abertos' => (clone $e)->where('first_seen_at', '>=', $start)->where('first_seen_at', '<', $until)->count(),
                'Eventos resolvidos' => (clone $e)->where('resolved_at', '>=', $start)->where('resolved_at', '<', $until)->count(),
            ];
        }
        $counts = (clone $checks)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $summary = [
            'Total de checks' => $counts->sum(), 'Listed' => $counts['listed'] ?? 0,
            'Clean' => $counts['clean'] ?? 0, 'Skipped' => $counts['skipped'] ?? 0,
            'Errors / timeouts' => ($counts['error'] ?? 0) + ($counts['timeout'] ?? 0),
            'Eventos abertos no período' => (clone $events)->where('first_seen_at', '>=', $start)->where('first_seen_at', '<', $until)->count(),
            'Eventos resolvidos no período' => (clone $events)->where('resolved_at', '>=', $start)->where('resolved_at', '<', $until)->count(),
        ];
        if ($request->input('format') === 'csv') {
            $filename = 'rbl-report-'.$start->toDateString().'-to-'.$until->subDay()->toDateString().'.csv';

            return response()->streamDownload(function () use ($checks, $summary, $groupSummary) {
                $output = fopen('php://output', 'w');
                $row = function (array $values) use ($output) {
                    $values = array_map(function ($value) {
                        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $value) ?? '';

                        return preg_match('/^\s*[=+@-]/u', $value) ? "'".$value : $value;
                    }, $values);
                    fputcsv($output, $values, ',', '"', '');
                };
                $row(['Métrica', 'Total']);
                foreach ($summary as $label => $total) {
                    $row([$label, $total]);
                }
                $row([]);
                if ($groupSummary) {
                    $row(array_keys($groupSummary[0]));
                    foreach ($groupSummary as $groupRow) {
                        $row(array_values($groupRow));
                    }
                    $row([]);
                }
                $row(['Grupo', 'Alvo', 'Valor verificado', 'RBL', 'Status', 'Data', 'Resposta', 'Erro']);
                foreach ((clone $checks)->with(['target.group', 'list'])->lazyById(500) as $check) {
                    $row([$check->target?->group?->name, $check->target?->name, $check->checked_value, $check->list?->name, $check->status,
                        $check->checked_at?->format('Y-m-d H:i:s'), $check->response, $check->error_message]);
                }
                fclose($output);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }
        $topTargets = (clone $checks)->where('status', 'listed')->selectRaw('rbl_target_id, checked_value, COUNT(*) AS total')
            ->groupBy('rbl_target_id', 'checked_value')->with('target')->orderByDesc('total')->orderBy('rbl_target_id')->limit(20)->get();
        $topLists = (clone $checks)->where('status', 'listed')->selectRaw('rbl_list_id, COUNT(*) AS total')
            ->groupBy('rbl_list_id')->with('list')->orderByDesc('total')->orderBy('rbl_list_id')->limit(20)->get();

        return view('rbl.reports', compact('summary', 'alertSummary', 'topTargets', 'topLists', 'groups', 'groupSummary'));
    }
}
