<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Setting;
use App\Models\SugestaoDominio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminSystemModulesPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestions_filter_search_and_preserve_query_string(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        foreach (range(1, 21) as $index) {
            SugestaoDominio::create([
                'empresa_id' => $empresa->id,
                'dominio' => sprintf('suspeito-%02d.example', $index),
                'motivo' => str_repeat('Motivo operacional ', 8),
                'status' => 'pending',
                'created_by' => $cliente->id,
            ]);
        }
        SugestaoDominio::create([
            'empresa_id' => $empresa->id,
            'dominio' => 'aprovado.example',
            'status' => 'approved',
            'created_by' => $cliente->id,
        ]);

        $response = $this->actingAs($admin)->get(route('sugestoes.index', [
            'status' => 'pending',
            'q' => 'suspeito',
        ]));

        $response->assertOk()
            ->assertSee('Pendente')
            ->assertDontSee('aprovado.example')
            ->assertSee('status=pending&amp;q=suspeito&amp;page=2', false)
            ->assertSee('Aprovar')
            ->assertSee('Rejeitar')
            ->assertSee('title="Motivo operacional', false);
    }

    public function test_decided_suggestion_does_not_show_decision_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        SugestaoDominio::create([
            'empresa_id' => $empresa->id,
            'dominio' => 'decidido.example',
            'status' => 'rejected',
            'created_by' => $cliente->id,
        ]);

        $this->actingAs($admin)->get(route('sugestoes.index', ['status' => 'rejected']))
            ->assertOk()
            ->assertSee('Rejeitada')
            ->assertDontSee('Aprovar')
            ->assertDontSee('Rejeitar esta sugestão?');
    }

    public function test_suggestions_have_contextual_empty_states(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('sugestoes.index', ['status' => 'pending']))
            ->assertSee('Nenhuma sugestão aguardando análise.');

        $this->actingAs($admin)->get(route('sugestoes.index', ['q' => 'inexistente']))
            ->assertSee('Nenhuma sugestão corresponde aos filtros selecionados.');
    }

    public function test_audit_uses_friendly_action_labels_without_n_plus_one(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (range(1, 20) as $index) {
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => $index === 1 ? 'empresa.updated' : 'auth.login',
                'target_type' => 'empresa',
                'target_id' => $index,
                'description' => 'Evento de auditoria',
                'ip_address' => '192.0.2.'.$index,
                'created_at' => now(),
            ]);
        }
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $response = $this->actingAs($admin)->get(route('auditoria.index'));

        $response->assertOk()
            ->assertSee('Empresa atualizada')
            ->assertSee('Login realizado')
            ->assertSee('title="empresa.updated"', false);
        $this->assertLessThan(10, $queries, 'Auditoria deve carregar ator e empresa sem N+1.');
    }

    public function test_security_metrics_and_empty_states_reflect_real_empty_data(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('seguranca.index'))
            ->assertOk()
            ->assertSee('0')
            ->assertSee('IPs bloqueados')
            ->assertSee('bloqueios nas últimas 24 h')
            ->assertSee('falhas de login')
            ->assertSee('Sem dados')
            ->assertSee('Nenhum IP bloqueado no momento.')
            ->assertSee('Nenhuma falha de login recente.')
            ->assertSee('Nenhum evento de bloqueio registrado.');
    }

    public function test_telegram_token_is_masked_and_test_feedback_is_clear(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::set('telegram_ativo', '1');
        Setting::set('telegram_bot_token', 'token-super-secreto');
        Setting::set('telegram_chat_id', '-100123');

        $this->actingAs($admin)->get(route('configuracoes.index'))
            ->assertOk()
            ->assertSee('Notificações')
            ->assertSee('Telegram — cadastro de empresa')
            ->assertSee('Como encontrar Chat ID e tópico')
            ->assertDontSee('token-super-secreto');

        Http::fakeSequence()
            ->push(['ok' => true])
            ->push(['ok' => false], 500);
        $this->actingAs($admin)->post(route('configuracoes.telegram.test'))
            ->assertSessionHas('status', 'Mensagem enviada com sucesso.');

        $this->actingAs($admin)->post(route('configuracoes.telegram.test'))
            ->assertSessionHas('error', 'Falha ao enviar mensagem. Confira token, chat ID e se a notificação está ativa.');
    }

    public function test_client_cannot_access_admin_system_endpoints_directly(): void
    {
        $cliente = User::factory()->cliente()->create();
        $sugestao = SugestaoDominio::create([
            'empresa_id' => $cliente->empresa_id,
            'dominio' => 'cliente.example',
            'status' => 'pending',
            'created_by' => $cliente->id,
        ]);
        $lista = Lista::factory()->create();

        $this->actingAs($cliente)->post(route('sugestoes.aprovar', $sugestao), ['lista_id' => $lista->id])->assertForbidden();
        $this->actingAs($cliente)->post(route('sugestoes.rejeitar', $sugestao))->assertForbidden();
        $this->actingAs($cliente)->get(route('auditoria.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('seguranca.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('configuracoes.index'))->assertForbidden();
        $this->actingAs($cliente)->put(route('configuracoes.telegram.update'))->assertForbidden();
        $this->actingAs($cliente)->post(route('configuracoes.telegram.test'))->assertForbidden();
    }
}
