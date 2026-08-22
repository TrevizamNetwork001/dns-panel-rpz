<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationTelegramTest extends TestCase
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

    public function test_registration_sends_telegram_notification_with_correct_payload(): void
    {
        config([
            'services.telegram.bot_token' => 'fake-token',
            'services.telegram.cadastros_chat_id' => '-1009999',
            'services.telegram.cadastros_thread_id' => '42',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->post(route('register.store'), $this->dadosCadastro());

        $response->assertRedirect(route('dashboard'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org/botfake-token/sendMessage')
                && $request['chat_id'] === '-1009999'
                && $request['message_thread_id'] === '42'
                && str_contains($request['text'], 'Provedor Teste')
                && str_contains($request['text'], 'Fulano de Tal')
                && str_contains($request['text'], 'fulano@provedor-teste.example');
        });
    }

    public function test_registration_succeeds_even_when_telegram_is_unreachable(): void
    {
        config([
            'services.telegram.bot_token' => 'fake-token',
            'services.telegram.cadastros_chat_id' => '-1009999',
            'services.telegram.cadastros_thread_id' => '42',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false], 500),
        ]);

        $response = $this->post(route('register.store'), $this->dadosCadastro());

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('empresas', ['nome' => 'Provedor Teste']);
    }

    public function test_registration_succeeds_when_telegram_is_not_configured(): void
    {
        config([
            'services.telegram.bot_token' => null,
            'services.telegram.cadastros_chat_id' => null,
        ]);

        Http::fake();

        $response = $this->post(route('register.store'), $this->dadosCadastro());

        $response->assertRedirect(route('dashboard'));
        Http::assertNothingSent();
    }
}
