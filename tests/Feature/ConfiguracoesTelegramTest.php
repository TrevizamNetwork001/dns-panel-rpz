<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Setting;
use App\Models\User;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConfiguracoesTelegramTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_configuracoes_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('configuracoes.index'))
            ->assertOk()
            ->assertSee('Instalação')
            ->assertSee('Proteções da aplicação')
            ->assertSee('Fontes gerenciadas')
            ->assertDontSee('Nada configurado aqui ainda.');
    }

    public function test_cliente_cannot_view_configuracoes_page(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();

        $this->actingAs($cliente)->get(route('configuracoes.index'))->assertStatus(403);
    }

    public function test_admin_can_save_telegram_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('configuracoes.telegram.update'), [
            'ativo' => '1',
            'bot_token' => 'meu-token-secreto',
            'chat_id' => '-1001234',
            'thread_id' => '99',
        ])->assertRedirect();

        $this->assertSame('meu-token-secreto', Setting::getEncrypted('telegram_bot_token'));
        $this->assertSame('-1001234', Setting::get('telegram_chat_id'));
        $this->assertSame('99', Setting::get('telegram_thread_id'));
        $this->assertSame('1', Setting::get('telegram_ativo'));
    }

    public function test_saving_without_new_token_keeps_the_existing_one(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::set('telegram_bot_token', 'token-ja-salvo');

        $this->actingAs($admin)->put(route('configuracoes.telegram.update'), [
            'ativo' => '1',
            'chat_id' => '-1009999',
        ]);

        $this->assertSame('token-ja-salvo', Setting::get('telegram_bot_token'));
    }

    public function test_unchecking_ativo_pauses_notifications(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::set('telegram_ativo', '1');

        $this->actingAs($admin)->put(route('configuracoes.telegram.update'), [
            'chat_id' => '-1009999',
        ]);

        $this->assertSame('0', Setting::get('telegram_ativo'));
    }

    public function test_test_button_sends_a_message_when_configured(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::set('telegram_ativo', '1');
        Setting::set('telegram_bot_token', 'fake-token');
        Setting::set('telegram_chat_id', '-1001234');

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $this->actingAs($admin)->post(route('configuracoes.telegram.test'))->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org/botfake-token/sendMessage'));
    }

    public function test_paused_notification_does_not_send_on_cadastro(): void
    {
        Setting::set('telegram_ativo', '0');
        Setting::set('telegram_bot_token', 'fake-token');
        Setting::set('telegram_chat_id', '-1001234');

        Http::fake();

        $this->post(route('register.store'), [
            'empresa_nome' => 'Provedor Pausado',
            'responsavel_nome' => 'Fulano',
            'email' => 'fulano@pausado.example',
            'password' => 'SenhaForte123!',
            'password_confirmation' => 'SenhaForte123!',
        ])->assertRedirect(route('dashboard'));

        Http::assertNothingSent();
    }

    public function test_health_problems_send_a_single_telegram_alert(): void
    {
        Setting::set('telegram_ativo', '1');
        Setting::set('telegram_bot_token', 'fake-token');
        Setting::set('telegram_chat_id', '-1001234');

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $sent = app(TelegramNotifier::class)->notifyHealthProblems([
            ['action' => 'health.disk_low', 'description' => 'Disco em 90% de uso'],
            ['action' => 'health.site_down', 'description' => 'Site indisponível'],
        ]);

        $this->assertTrue($sent);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['text'], 'Disco em 90% de uso')
            && str_contains($request['text'], 'Site indisponível'));
    }
}
