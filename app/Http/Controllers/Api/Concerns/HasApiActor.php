<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait HasApiActor
{
    protected function isAdmin(Request $request): bool
    {
        return (bool) $request->attributes->get('api_is_admin', false);
    }

    /**
     * ID da empresa a que o request está restrito, ou null se for admin (sem restrição).
     */
    protected function empresaId(Request $request): ?int
    {
        return $request->attributes->get('api_empresa_id');
    }

    protected function abortUnlessAdmin(Request $request): void
    {
        if (! $this->isAdmin($request)) {
            abort(403, 'Ação restrita ao administrador.');
        }
    }

    /**
     * Confere se o request (admin ou dono) pode acessar um recurso de uma empresa especifica.
     */
    protected function abortUnlessOwnerOrAdmin(Request $request, ?int $recursoEmpresaId): void
    {
        if ($this->isAdmin($request)) {
            return;
        }

        if ($recursoEmpresaId === null || $recursoEmpresaId !== $this->empresaId($request)) {
            abort(403, 'Você não tem acesso a este recurso.');
        }
    }
}
