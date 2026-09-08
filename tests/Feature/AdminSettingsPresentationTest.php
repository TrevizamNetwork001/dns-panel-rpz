<?php

namespace Tests\Feature;

use App\Models\Lista;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminSettingsPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabs_inventory_and_administration_keep_data_and_destinations(): void
    {
        config(['app.name' => 'DNS Panel RPZ', 'app.url' => 'https://panel.example.com', 'app.timezone' => 'America/Sao_Paulo']);
        $admin = User::factory()->admin()->create();
        foreach (['geral', 'notificacoes', 'seguranca', 'integracoes'] as $tab) {
            $response = $this->actingAs($admin)->get(route('configuracoes.index', ['aba' => $tab]))->assertOk();
            $response->assertSee('data-settings-tab="'.$tab.'"', false)
                ->assertSee('aria-controls="settings-panel-'.$tab.'"', false)
                ->assertSee('data-settings-panel="'.$tab.'"', false);
        }
        foreach (['Geral', 'Notificações', 'Segurança', 'Integrações', 'Nome da aplicação', 'DNS Panel RPZ', 'https://panel.example.com', 'America/Sao_Paulo', 'testing', 'Operacional', '.env'] as $text) {
            $response->assertSeeText($text);
        }
        foreach (['usuarios.index', 'auditoria.index', 'seguranca.index'] as $route) {
            $response->assertSee('href="'.route($route).'"', false);
        }
        $response->assertDontSee('class="metric-card"')->assertDontSee('<table', false);
    }

    public function test_telegram_states_secret_and_form_contracts_are_preserved(): void
    {
        Http::fake();
        $admin = User::factory()->admin()->create();
        Setting::set('telegram_bot_token', 'secret-must-never-be-in-html');
        Setting::set('telegram_chat_id', '-1001234567890');
        Setting::set('telegram_thread_id', '42');
        foreach (['1' => 'Ativa', '0' => 'Inativa'] as $enabled => $label) {
            Setting::set('telegram_ativo', (string) $enabled);
            $response = $this->actingAs($admin)->get(route('configuracoes.index', ['aba' => 'notificacoes']))->assertOk();
            $response->assertSeeText($label)->assertDontSee('secret-must-never-be-in-html')
                ->assertSeeText('Já configurado · deixe em branco para manter')
                ->assertSeeText('ID do tópico (opcional)')->assertSeeText('Salvar alterações')
                ->assertSeeText('Enviar teste')->assertSeeText('Como encontrar Chat ID e tópico')
                ->assertSeeText('chat.id')->assertSeeText('message_thread_id')
                ->assertSeeText('/cadastro')->assertSeeText('Inclui empresa, responsável e e-mail.')
                ->assertSeeText('Falhas no Telegram não interrompem o cadastro.');
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $token = $xpath->query('//input[@name="bot_token"]')[0];
            $this->assertSame('password', $token->getAttribute('type'));
            $this->assertSame('', $token->getAttribute('value'));
            $this->assertSame('-1001234567890', $xpath->query('//input[@name="chat_id"]')[0]->getAttribute('value'));
            $this->assertSame('42', $xpath->query('//input[@name="thread_id"]')[0]->getAttribute('value'));
            $this->assertSame((bool) $enabled, $xpath->query('//input[@name="ativo"]')[0]->hasAttribute('checked'));
            $form = $xpath->query('//form[@id="settings-telegram-save"]')[0];
            $this->assertSame(route('configuracoes.telegram.update'), $form->getAttribute('action'));
            $this->assertSame('POST', $form->getAttribute('method'));
            $this->assertSame('PUT', $xpath->query('.//input[@name="_method"]', $form)[0]->getAttribute('value'));
            $this->assertCount(1, $xpath->query('//button[@form="settings-telegram-save"]'));
            $this->assertCount(1, $xpath->query('//form[@action="'.route('configuracoes.telegram.test').'"][@method="POST"]'));
            $this->assertFalse($xpath->query('//details')[0]->hasAttribute('open'));
        }
        Http::assertNothingSent();
    }

    public function test_security_summary_retains_all_four_protections_and_monitoring_link(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('configuracoes.index', ['aba' => 'seguranca']))->assertOk();
        foreach (['Proteções da aplicação', 'Login', 'Limite de tentativas e registro de falhas', 'Endpoints RPZ', 'Limite de 60 requisições por minuto e controle por IP', 'Permissões', 'Separação entre administradores e empresas clientes', 'Auditoria', 'Registro de acessos e alterações sensíveis', 'Monitoramento', 'Abrir Segurança'] as $text) {
            $response->assertSeeText($text);
        }
        $response->assertSee('href="'.route('seguranca.index').'"', false);
    }

    public function test_integration_metrics_keep_source_selection_and_latest_sync(): void
    {
        Lista::factory()->externa()->count(2)->create(['last_sync_at' => '2026-09-08 06:15:00']);
        Lista::factory()->anatel()->create(['last_sync_at' => '2026-09-07 06:15:00']);
        Lista::factory()->create(['last_sync_at' => '2026-09-09 06:15:00']);
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('configuracoes.index'))->assertOk();
        $data = $response->viewData('integracoes');
        $this->assertSame(3, $data['total']);
        $this->assertSame(2, $data['ativas']);
        $this->assertSame(1, $data['pausadas']);
        foreach (['Fontes gerenciadas', 'Sincronizações ativas', 'Fontes pausadas', '08/09/2026 06:15', 'URLs, formatos e pausas são administrados diretamente em cada fonte.', 'Gerenciar fontes'] as $text) {
            $response->assertSeeText($text);
        }
        $response->assertSee('href="'.route('listas.index').'"', false);
    }

    public function test_empty_integrations_and_query_count_remain_bounded(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('configuracoes.index'))
            ->assertOk()->assertSeeText('ainda não executada');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('configuracoes.index'))->assertOk();
        $smallCount = count(DB::getQueryLog());
        Lista::factory()->externa()->count(100)->create();
        DB::flushQueryLog();
        $this->get(route('configuracoes.index'))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame($smallCount, count($queries));
        $this->assertCount(1, array_filter($queries, fn ($q) => str_contains($q['query'], 'from "listas"')));
    }

    public function test_admin_protection_remains_on_all_settings_routes(): void
    {
        $routes = ['configuracoes.index' => 'get', 'configuracoes.telegram.update' => 'put', 'configuracoes.telegram.test' => 'post'];
        foreach ($routes as $name => $method) {
            $middleware = app('router')->getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertContains('admin', $middleware);
            $this->{$method}(route($name))->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->cliente()->create());
        foreach ($routes as $name => $method) {
            $this->{$method}(route($name))->assertForbidden();
        }
    }
}
