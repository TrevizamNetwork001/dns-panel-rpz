<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\EmpresaDomainException;
use App\Services\RpzExceptionPolicy;
use App\Services\RpzZoneBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class EmpresaExceptionController extends Controller
{
    public function index(Empresa $empresa): View
    {
        $exceptions = EmpresaDomainException::where('empresa_id', $empresa->id)->orderBy('dominio')->paginate(50);

        return view('empresas.exceptions', compact('empresa', 'exceptions'));
    }

    public function store(Request $request, Empresa $empresa, RpzZoneBuilder $builder): RedirectResponse
    {
        $data = $request->validate(['dominio' => ['required', 'string', 'max:253'], 'motivo' => ['nullable', 'string', 'max:255']]);
        try {
            $data['dominio'] = RpzExceptionPolicy::validateForZone($data['dominio'], $builder->panelHost());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['dominio' => $e->getMessage()]);
        }
        $existing = EmpresaDomainException::where('empresa_id', $empresa->id)->pluck('dominio');
        if ($existing->count() >= 1000) {
            throw ValidationException::withMessages(['dominio' => 'Limite de 1.000 exceções por empresa atingido.']);
        }
        foreach ($existing as $domain) {
            if (RpzExceptionPolicy::normalize($domain) === $data['dominio']) {
                throw ValidationException::withMessages(['dominio' => 'Este domínio já possui uma exceção nesta empresa.']);
            }
        }
        try {
            DB::transaction(function () use ($empresa, $data): void {
                $exception = EmpresaDomainException::create(['empresa_id' => $empresa->id] + $data);
                AuditLog::record('empresa.exception.created', 'Exceção empresarial adicionada: '.$exception->dominio, $empresa->id, 'empresa_domain_exception', $exception->id);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['dominio' => 'Este domínio já possui uma exceção nesta empresa.']);
        }

        return redirect()->route('empresas.exceptions.index', $empresa)->with('status', 'Exceção adicionada para esta empresa.');
    }

    public function destroy(Empresa $empresa, EmpresaDomainException $exception): RedirectResponse
    {
        abort_unless($exception->empresa_id === $empresa->id, 404);
        DB::transaction(function () use ($empresa, $exception): void {
            AuditLog::record('empresa.exception.deleted', 'Exceção empresarial removida: '.$exception->dominio, $empresa->id, 'empresa_domain_exception', $exception->id);
            $exception->delete();
        });

        return redirect()->route('empresas.exceptions.index', $empresa)->with('status', 'Exceção removida.');
    }
}
