<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAuditPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_preserves_metrics_labels_raw_actions_and_searchable_metadata(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        $empresa = Empresa::factory()->create(['nome' => 'Empresa teste']);
        $labels = [
            'auth.login' => 'Login realizado',
            'auth.logout' => 'Sessão encerrada',
            'user.password_reset' => 'Senha de usuário redefinida',
            'rpz.endpoint.downloaded' => 'Zona RPZ baixada',
            'health.ok' => 'Health ok',
            'custom.action_name' => 'Custom action name',
            'auth.login_failed' => 'Falha de login',
            'sugestao.created' => 'Sugestão recebida',
        ];
        foreach ($labels as $action => $label) {
            AuditLog::create([
                'user_id' => $admin->id, 'empresa_id' => $empresa->id,
                'action' => $action, 'description' => 'Evento de teste', 'target_type' => 'empresa', 'target_id' => 11,
                'ip_address' => '168.194.14.100', 'created_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('auditoria.index'))->assertOk();
        foreach (['Eventos exibidos', 'Eventos recentes (24h)', 'Falhas de autenticação', 'Ações sensíveis', 'Severidade'] as $text) {
            $response->assertSeeText($text);
        }
        $response->assertDontSee('Categoria')->assertDontSee('Audit log')
            ->assertDontSee('<table', false)->assertSee('<ul class="audit-stream"', false)
            ->assertSee('Nenhum evento corresponde à busca.')
            ->assertViewHas('visibleCount', 8)->assertViewHas('recentCount', 8)
            ->assertViewHas('authFailureCount', 1)->assertViewHas('destructiveCount', 2);
        foreach ($labels as $action => $label) {
            $response->assertSee('title="'.$action.'"', false)->assertSeeText($label);
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $events = (new \DOMXPath($dom))->query('//li[@data-audit-search]');
        $this->assertCount(8, $events);
        foreach ($events as $event) {
            $search = $event->getAttribute('data-audit-search');
            foreach (['admin', 'empresa #11', 'empresa teste', '168.194.14.100', now()->format('d/m/Y')] as $term) {
                $this->assertStringContainsString($term, $search);
            }
            $this->assertSame(mb_strtolower($search), $search);
            $this->assertStringContainsString('Severidade:', $event->textContent);
        }
        foreach (['Alto', 'Médio', 'Info', 'Baixo'] as $severity) {
            $response->assertSee('Severidade: '.$severity);
        }
        $this->assertSame(2, substr_count($response->getContent(), ' audit-event-routine"'));
        $this->assertDatabaseCount('audit_logs', 8);
    }

    public function test_latest_200_events_eager_load_relations_without_query_growth(): void
    {
        $admin = User::factory()->admin()->create();
        $actors = User::factory()->count(3)->create();
        $empresas = Empresa::factory()->count(3)->create();
        $addLogs = function (int $count) use ($actors, $empresas): void {
            foreach (range(1, $count) as $index) {
                AuditLog::create([
                    'user_id' => $actors[$index % 3]->id, 'empresa_id' => $empresas[$index % 3]->id,
                    'action' => 'health.ok', 'description' => 'Evento de teste', 'created_at' => now(),
                ]);
            }
        };
        $addLogs(3);
        $this->actingAs($admin)->get(route('auditoria.index'))->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('auditoria.index'))->assertOk();
        $smallQueryCount = count(DB::getQueryLog());
        $addLogs(202);
        DB::flushQueryLog();
        $response = $this->get(route('auditoria.index'))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame($smallQueryCount, count($queries));
        $this->assertCount(1, array_filter($queries, fn ($query) => str_contains($query['query'], 'from "audit_logs"')));
        $logs = $response->viewData('visibleLogs');
        $this->assertCount(200, $logs);
        $this->assertSame(range(205, 6), $logs->pluck('id')->all());
        foreach ($logs as $log) {
            $this->assertTrue($log->relationLoaded('user'));
            $this->assertTrue($log->relationLoaded('empresa'));
        }
        $this->assertSame(200, substr_count($response->getContent(), '<li class="audit-event '));
        $response->assertViewHas('visibleCount', 200);
        $this->assertDatabaseCount('audit_logs', 205);
    }

    public function test_backend_filters_and_empty_state_remain_available(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (['auth.login', 'auth.login_failed', 'health.ok'] as $action) {
            AuditLog::create(['action' => $action, 'description' => 'Evento de teste', 'created_at' => now()]);
        }
        $this->actingAs($admin)->get(route('auditoria.index', ['q' => 'auth', 'bucket' => 'danger']))
            ->assertOk()->assertViewHas('visibleCount', 1)->assertSeeText('Falha de login')
            ->assertDontSee('title="auth.login"', false)
            ->assertSee('name="q"', false)->assertSee('name="bucket"', false)->assertSeeText('Aplicar');
        $this->get(route('auditoria.index', ['q' => 'inexistente']))->assertOk()
            ->assertSeeText('Nenhum evento encontrado')->assertDontSee('<ul class="audit-stream"', false);
    }

    public function test_audit_route_remains_protected_for_guests_and_clients(): void
    {
        $route = app('router')->getRoutes()->getByName('auditoria.index');
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('admin', $route->gatherMiddleware());
        $this->get(route('auditoria.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->cliente()->create())->get(route('auditoria.index'))->assertForbidden();
    }
}
