<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Confere o token do Cloudflare Turnstile direto com a API da Cloudflare.
 * Se TURNSTILE_SECRET_KEY não estiver configurado, a checagem é ignorada
 * silenciosamente (não trava cadastro em ambiente sem a chave configurada).
 * O controller adiciona 'required' condicionalmente antes desta regra --
 * uma ValidationRule comum não roda sozinha se o campo vier ausente.
 */
class ValidTurnstileToken implements ValidationRule
{
    public function __construct(private ?string $remoteIp) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.turnstile.secret_key');

        if (! $secret) {
            return;
        }

        if (! is_string($value) || $value === '') {
            $fail('Confirme que você não é um robô.');

            return;
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $value,
                'remoteip' => $this->remoteIp,
            ]);

            if (! $response->successful() || $response->json('success') !== true) {
                $fail('Confirme que você não é um robô.');
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao verificar Turnstile', ['erro' => 'Falha de comunicação com Cloudflare.']);
            $fail('Não foi possível confirmar que você não é um robô. Tente novamente.');
        }
    }
}
