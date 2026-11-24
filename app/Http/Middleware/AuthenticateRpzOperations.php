<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateRpzOperations
{
    public const TOKEN_HASH_KEY = 'rpz_operations_token_hash';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $validFormat = is_string($token) && preg_match('/^rpzo_[a-f0-9]{64}$/D', $token);
        $hash = $validFormat ? Setting::get(self::TOKEN_HASH_KEY) : null;

        abort_unless(
            is_string($hash) && preg_match('/^[a-f0-9]{64}$/D', $hash)
            && hash_equals($hash, hash('sha256', $token)),
            401,
            'Credencial administrativa de leitura inválida.',
            ['WWW-Authenticate' => 'Bearer'],
        );

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
