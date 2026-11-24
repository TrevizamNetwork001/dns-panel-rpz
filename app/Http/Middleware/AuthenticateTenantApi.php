<?php

namespace App\Http\Middleware;

use App\Models\TenantApiCredential;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTenantApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_unless(is_string($token) && preg_match('/^rpzk_[a-zA-Z0-9]{64}$/D', $token), 401, 'Credencial inválida.', ['WWW-Authenticate' => 'Bearer']);

        $credential = TenantApiCredential::with('user.empresa')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        $user = $credential?->user;
        abort_unless($user?->isCliente() && $user->empresa_id === $credential->empresa_id
            && $user->empresa?->status === 'active', 401, 'Credencial inválida.', ['WWW-Authenticate' => 'Bearer']);

        $allowed = match ($credential->scope) {
            'report' => $request->routeIs('tenant-api.server-report'),
            'write' => ! $request->routeIs('tenant-api.server-report'),
            'read' => in_array($request->method(), ['GET', 'HEAD'], true),
            default => false,
        };
        abort_unless($allowed, 403, 'Credencial sem permissão para esta operação.');
        $request->attributes->set('tenant_api_server_id', $credential->servidor_id);

        Auth::setUser($user);
        $request->attributes->set('tenant_api_credential_id', $credential->id);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
