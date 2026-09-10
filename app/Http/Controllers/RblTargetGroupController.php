<?php

namespace App\Http\Controllers;

use App\Models\RblCheck;
use App\Models\RblEvent;
use App\Models\RblTargetGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RblTargetGroupController extends Controller
{
    public function index()
    {
        return view('rbl.groups.index', ['groups' => RblTargetGroup::withCount('targets')->orderBy('name')->paginate(25)]);
    }

    public function create()
    {
        return view('rbl.groups.form', ['group' => new RblTargetGroup(['enabled' => true])]);
    }

    public function edit(RblTargetGroup $group)
    {
        return view('rbl.groups.form', compact('group'));
    }

    private function data(Request $request, ?RblTargetGroup $group = null): array
    {
        if (! $request->filled('slug')) {
            $request->merge(['slug' => Str::slug($request->input('name', ''))]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('rbl_target_groups')->ignore($group)],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', Rule::in(['cgnat', 'mail', 'infra', 'dedicated', 'other'])],
            'enabled' => ['required', 'boolean'],
        ]);
    }

    public function store(Request $request)
    {
        $group = RblTargetGroup::create($this->data($request));

        return redirect()->route('rbl.groups.show', $group)->with('status', 'Grupo cadastrado.');
    }

    public function update(Request $request, RblTargetGroup $group)
    {
        $group->update($this->data($request, $group));

        return redirect()->route('rbl.groups.show', $group)->with('status', 'Grupo atualizado.');
    }

    public function toggle(RblTargetGroup $group)
    {
        $group->update(['enabled' => ! $group->enabled]);

        return back()->with('status', 'Grupo atualizado.');
    }

    public function show(RblTargetGroup $group)
    {
        $events = RblEvent::whereHas('target', fn ($q) => $q->where('rbl_target_group_id', $group->id));
        $cidrTargets = $group->targets()->where('type', 'cidr')->with('scanState')->get();
        $scanSummary = [
            'blocks' => $cidrTargets->count(),
            'total_ips' => $cidrTargets->sum(fn ($target) => $target->scanState?->total_ips ?? $target->cidrTotalIps()),
            'scanned_ips' => $cidrTargets->sum(fn ($target) => $target->scanState?->scanned_ips ?? 0),
            'listed_ips' => $cidrTargets->sum(fn ($target) => $target->scanState?->listed_ips ?? 0),
            'pending_ips' => $cidrTargets->sum(fn ($target) => $target->scanState?->pendingIps() ?? $target->cidrTotalIps()),
        ];

        return view('rbl.groups.show', [
            'group' => $group, 'total' => $group->targets()->count(),
            'listed' => $group->targets()->where('last_status', 'listed')->count(),
            'open' => (clone $events)->where('status', 'open')->count(),
            'lastCheck' => RblCheck::whereHas('target', fn ($q) => $q->where('rbl_target_group_id', $group->id))->latest('id')->first(),
            'targets' => $group->targets()->orderBy('name')->paginate(25),
            'events' => $events->with(['target.group', 'list', 'alerts'])->latest('last_seen_at')->limit(10)->get(),
            'scanSummary' => $scanSummary,
        ]);
    }
}
