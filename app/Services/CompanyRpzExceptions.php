<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\EmpresaDomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CompanyRpzExceptions
{
    public function __construct(private RpzZoneBuilder $builder) {}

    public function normalize(string $domain): string
    {
        try {
            return RpzExceptionPolicy::validateForZone($domain, $this->builder->panelHost());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['dominio' => $exception->getMessage()]);
        }
    }

    public function create(Empresa $company, string $domain, ?string $reason, ?string $requestId = null): EmpresaDomainException
    {
        $domain = $this->normalize($domain);

        try {
            return DB::transaction(function () use ($company, $domain, $reason, $requestId): EmpresaDomainException {
                $existing = EmpresaDomainException::where('empresa_id', $company->id)->pluck('dominio');
                if ($existing->count() >= 1000) {
                    throw ValidationException::withMessages(['dominio' => 'Limite de 1.000 exceções por empresa atingido.']);
                }
                if ($existing->contains(fn (string $stored): bool => RpzExceptionPolicy::normalize($stored) === $domain)) {
                    throw ValidationException::withMessages(['dominio' => 'Este domínio já possui uma exceção nesta empresa.']);
                }

                $exception = EmpresaDomainException::create([
                    'empresa_id' => $company->id,
                    'dominio' => $domain,
                    'motivo' => $reason,
                ]);
                $this->record($exception, 'created', 'adicionada', $requestId);

                return $exception;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['dominio' => 'Este domínio já possui uma exceção nesta empresa.']);
        }
    }

    public function remove(Empresa $company, int $id, ?string $requestId = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($company, $id, $requestId, $reason): void {
            $exception = EmpresaDomainException::where('empresa_id', $company->id)->lockForUpdate()->findOrFail($id);
            $this->record($exception, 'deleted', 'removida', $requestId, $reason);
            $exception->delete();
        });
    }

    private function record(EmpresaDomainException $exception, string $action, string $label, ?string $requestId, ?string $reason = null): void
    {
        $description = "Exceção empresarial {$label}: ".$exception->dominio;
        if ($requestId !== null) {
            $description .= ' (Telegram, solicitação '.$requestId.')';
        }
        if ($reason !== null) {
            $description .= ' — Motivo: '.$reason;
        }
        AuditLog::record('empresa.exception.'.$action, $description, $exception->empresa_id, 'empresa_domain_exception', $exception->id);
    }
}
