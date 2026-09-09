<?php

namespace App\Http\Controllers;

use App\Models\RblCheck;
use App\Models\RblEvent;
use App\Models\RblList;
use App\Models\RblRun;
use App\Models\RblTarget;
use App\Models\RblTargetGroup;
use App\Services\Rbl\DnsblResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RblController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['group' => ['nullable', 'integer', 'exists:rbl_target_groups,id']]);

        return view('rbl.index', [
            'groups' => RblTargetGroup::withCount(['targets', 'targets as listed_count' => fn ($q) => $q->where('last_status', 'listed'), 'events as open_count' => fn ($q) => $q->where('status', 'open')])->orderBy('name')->get(),
            'targets' => RblTarget::with('group')->when($request->input('group'), fn ($q, $id) => $q->where('rbl_target_group_id', $id))->latest('id')->paginate(25)->withQueryString(),
            'lists' => RblList::orderBy('name')->get(),
            'total' => RblTarget::where('enabled', true)->count(),
            'listed' => RblTarget::where('enabled', true)->where('last_status', 'listed')->count(),
            'open' => RblEvent::where('status', 'open')->count(),
            'lastRun' => RblRun::latest('id')->first(),
            'resolved24h' => RblEvent::where('status', 'resolved')->where('resolved_at', '>=', now()->subDay())->count(),
            'errors24h' => RblCheck::whereIn('status', ['error', 'timeout'])->where('checked_at', '>=', now()->subDay())->count(),
            'openEvents' => RblEvent::with(['target.group', 'list'])->where('status', 'open')->latest('last_seen_at')->limit(10)->get(),
            'resolvedEvents' => RblEvent::with(['target.group', 'list'])->where('status', 'resolved')->latest('resolved_at')->limit(10)->get(),
            'errorChecks' => RblCheck::with(['target.group', 'list'])->whereIn('status', ['error', 'timeout'])->latest('checked_at')->limit(10)->get(),
            'uncheckedTargets' => RblTarget::where('enabled', true)->whereNull('last_checked_at')->orderBy('id')->limit(10)->get(),
        ]);
    }

    public function storeList(Request $request)
    {
        if (is_string($request->input('dns_zone'))) {
            $request->merge(['dns_zone' => strtolower(trim($request->input('dns_zone')))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'dns_zone' => ['required', 'string', 'max:237', 'unique:rbl_lists,dns_zone', function ($attribute, $value, $fail) {
                if (! DnsblResolver::validName($value)) {
                    $fail('Informe uma zona DNS válida, sem protocolo ou caminho.');
                }
            }],
            'type' => ['required', Rule::in(['ip', 'domain'])],
            'timeout_seconds' => ['required', 'integer', 'between:1,5'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['dns_zone'] = strtolower($data['dns_zone']);
        $data['enabled'] = false;
        RblList::create($data);

        return redirect()->route('rbl.index')->with('status', 'Lista cadastrada desativada. Ative após confirmar as condições de acesso do provedor.');
    }

    public function toggleList(RblList $list)
    {
        $list->update(['enabled' => ! $list->enabled]);

        return back()->with('status', 'Lista RBL atualizada.');
    }
}
