<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAuthenticated
{
    /**
     * Autentica APENAS por token pessoal (Bearer) ou chave de empresa (X-Api-Key).
     *
     * Não usa o guard 'sanctum' padrão de propósito: por padrão ele cai pra sessão
     * web antes de checar o token, o que faria um admin logado no navegador acessar
     * a API sem token nenhum (e sem proteção CSRF). A API aqui é so-token.
     *
     * Se a empresa do ator (dona do token ou da chave) tiver restrição de IP
     * habilitada, o IP de origem precisa estar na ACL — mesma lógica usada
     * hoje pros servidores buscarem o zonefile RPZ.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken) {
            $accessToken = PersonalAccessToken::findToken($bearerToken);

            if ($accessToken && $this->tokenIsValid($accessToken)) {
                $user = $accessToken->tokenable;

                if (! $user->isAdmin() && ($erro = $this->ipBlockedResponse($request, $user->empresa))) {
                    return $erro;
                }

                $accessToken->forceFill(['last_used_at' => now()])->save();

                $request->attributes->set('api_user', $user);
                $request->attributes->set('api_is_admin', $user->isAdmin());
                $request->attributes->set('api_empresa_id', $user->isAdmin() ? null : $user->empresa_id);

                return $next($request);
            }
        }

        $apiKey = $request->header('X-Api-Key');

        if ($apiKey) {
            $empresa = Empresa::where('api_key', $apiKey)->where('status', 'active')->first();

            if ($empresa) {
                if ($erro = $this->ipBlockedResponse($request, $empresa)) {
                    return $erro;
                }

                $request->attributes->set('api_user', null);
                $request->attributes->set('api_is_admin', false);
                $request->attributes->set('api_empresa_id', $empresa->id);
                $request->attributes->set('api_empresa', $empresa);

                return $next($request);
            }
        }

        return response()->json(['message' => 'Não autenticado. Use um token pessoal (Authorization: Bearer) ou a chave da empresa (X-Api-Key).'], 401);
    }

    private function ipBlockedResponse(Request $request, ?Empresa $empresa): ?Response
    {
        if (! $empresa || $empresa->ipAllowed($request->ip())) {
            return null;
        }

        return response()->json(['message' => 'IP não autorizado para acessar a API em nome desta empresa.'], 403);
    }

    private function tokenIsValid(PersonalAccessToken $accessToken): bool
    {
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return false;
        }

        return $accessToken->tokenable !== null;
    }
}
