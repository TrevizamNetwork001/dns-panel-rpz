<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationTurnstileTest extends TestCase
{
    use RefreshDatabase;

    private function dadosCadastro(array $overrides = []): array
    {
        return array_merge([
            'empresa_nome' => 'Provedor Teste',
            'responsavel_nome' => 'Fulano de Tal',
            'email' => 'fulano@provedor-teste.example',
            'password' => 'SenhaForte123!',
            'password_confirmation' => 'SenhaForte123!',
        ], $overrides);
    }

    public function test_registration_succeeds_without_turnstile_configured(): void
    {
        config(['services.turnstile.secret_key' => null]);

        Http::fake();

        $response = $this->post(route('register.store'), $this->dadosCadastro());

        $response->assertRedirect(route('dashboard'));
        Http::assertNothingSent();
    }

    public function test_registration_fails_without_token_when_turnstile_configured(): void
    {
        config(['services.turnstile.secret_key' => 'fake-secret']);

        Http::fake();

        $response = $this->post(route('register.store'), $this->dadosCadastro());

        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseMissing('empresas', ['nome' => 'Provedor Teste']);
    }

    public function test_registration_succeeds_with_valid_turnstile_token(): void
    {
        config(['services.turnstile.secret_key' => 'fake-secret']);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->post(route('register.store'), $this->dadosCadastro(['cf-turnstile-response' => 'token-valido']));

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('empresas', ['nome' => 'Provedor Teste']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'challenges.cloudflare.com')
            && $request['secret'] === 'fake-secret'
            && $request['response'] === 'token-valido');
    }

    public function test_registration_fails_when_cloudflare_rejects_token(): void
    {
        config(['services.turnstile.secret_key' => 'fake-secret']);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']], 200),
        ]);

        $response = $this->post(route('register.store'), $this->dadosCadastro(['cf-turnstile-response' => 'token-invalido']));

        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseMissing('empresas', ['nome' => 'Provedor Teste']);
    }

    public function test_registration_fails_gracefully_when_cloudflare_is_unreachable(): void
    {
        config(['services.turnstile.secret_key' => 'fake-secret']);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(null, 500),
        ]);

        $response = $this->post(route('register.store'), $this->dadosCadastro(['cf-turnstile-response' => 'qualquer-token']));

        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseMissing('empresas', ['nome' => 'Provedor Teste']);
    }
}
