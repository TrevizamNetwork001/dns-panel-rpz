<?php

namespace App\Http\Controllers;

use App\Models\RblTarget;
use App\Models\RblTargetGroup;
use App\Services\Rbl\DnsblResolver;
use App\Services\Rbl\RblChecker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class RblTargetController extends Controller
{
    public function create()
    {
        return view('rbl.create', ['target' => new RblTarget(['enabled' => true]), 'groups' => RblTargetGroup::orderBy('name')->get()]);
    }

    public function edit(RblTarget $target)
    {
        return view('rbl.create', ['target' => $target, 'groups' => RblTargetGroup::orderBy('name')->get()]);
    }

    public function update(Request $request, RblTarget $target)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rbl_target_group_id' => ['nullable', 'integer', 'exists:rbl_target_groups,id'],
            'category' => ['nullable', Rule::in(['cgnat', 'mail', 'infra', 'dedicated', 'other'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['required', 'boolean'],
        ]);
        $target->update($data);

        return redirect()->route('rbl.targets.show', $target)->with('status', 'Alvo atualizado.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['ip', 'cidr', 'domain', 'hostname'])],
            'value' => ['required', 'string', 'max:253', function ($attribute, $value, $fail) use ($request) {
                $valid = match ($request->input('type')) {
                    'ip' => filter_var($value, FILTER_VALIDATE_IP) !== false,
                    'cidr' => $this->validCidr($value),
                    'domain', 'hostname' => DnsblResolver::validName($value),
                    default => false,
                };
                if (! $valid) {
                    $fail('Informe um valor válido para o tipo de alvo selecionado.');
                }
            }],
            'rbl_target_group_id' => ['nullable', 'integer', 'exists:rbl_target_groups,id'],
            'category' => ['nullable', Rule::in(['cgnat', 'mail', 'infra', 'dedicated', 'other'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['required', 'boolean'],
        ]);
        $data['value'] = strtolower($data['value']);
        $target = RblTarget::create($data);

        return redirect()->route('rbl.targets.show', $target)->with('status', 'Alvo cadastrado.');
    }

    private function validCidr(string $value): bool
    {
        $parts = explode('/', $value);

        return count($parts) === 2 && filter_var($parts[0], FILTER_VALIDATE_IP)
            && ctype_digit($parts[1]) && (int) $parts[1] <= (str_contains($parts[0], ':') ? 128 : 32);
    }

    public function show(RblTarget $target)
    {
        return view('rbl.show', [
            'target' => $target,
            'checks' => $target->checks()->with('list')->latest('id')->paginate(25, ['*'], 'checks_page'),
            'events' => $target->events()->with('list')->latest('id')->paginate(25, ['*'], 'events_page'),
            'listedChecks' => $target->checks()->with('list')->whereIn('id',
                $target->checks()->selectRaw('MAX(id)')->groupBy('rbl_list_id', 'checked_value')
            )->where('status', 'listed')->get(),
        ]);
    }

    public function toggle(RblTarget $target)
    {
        $target->update(['enabled' => ! $target->enabled]);

        return back()->with('status', 'Alvo atualizado.');
    }

    public function check(RblTarget $target, RblChecker $checker)
    {
        try {
            $target = $checker->check($target);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['check' => 'Não foi possível concluir a verificação. Tente novamente.']);
        }

        return redirect()->route('rbl.targets.show', $target)->with('status', match ($target->last_status) {
            'listed' => 'Verificação concluída: alvo listado. Consulte os resultados por lista.',
            'error' => 'Verificação registrada com erros DNS. Consulte o histórico.',
            'unchecked', 'skipped' => 'Verificação registrada com consultas ignoradas (skipped). Consulte o histórico.',
            default => 'Verificação concluída: alvo limpo nas listas consultadas.',
        });
    }
}
