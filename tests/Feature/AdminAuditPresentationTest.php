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
            'user.password_reset' => 'Senha de usuário redefinida por um admin',
            'rpz.endpoint.downloaded' => 'Servidor consultou a lista RPZ',
            'health.ok' => 'Verificação do sistema: tudo normal',
            'custom.action_name' => 'Custom action name',
            'auth.login_failed' => 'Falha de login',
            'sugestao.created' => 'Sugestão de domínio recebida',
        ];
        foreach ($labels as $action => $label) {
            AuditLog::create([
                'user_id' => $admin->id, 'empresa_id' => $empresa->id,
                'action' => $action, 'description' => 'Evento de teste', 'target_type' => 'empresa', 'target_id' => 11,
                'ip_address' => '198.51.100.100', 'created_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('auditoria.index'))->assertOk();
        foreach (['Eventos encontrados', 'Eventos recentes (24h)', 'Falhas de autenticação', 'Ações sensíveis', 'Severidade', 'Categoria'] as $text) {
            $response->assertSeeText($text);
        }
        $response->assertDontSee('Audit log')
            ->assertDontSee('<table', false)->assertSee('<ul class="audit-stream"', false)
            ->assertSee('Nenhum evento corresponde à busca.')
            ->assertViewHas('totalFiltered', 8)->assertViewHas('recentCount', 8)
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
            foreach (['admin', 'empresa #11', 'empresa teste', '198.51.100.100', now()->format('d/m/Y')] as $term) {
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

    public function test_events_beyond_page_size_are_paginated_without_query_growth(): void
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
        $this->assertSame(50, $logs->count(), 'A primeira página deve trazer 50 eventos.');
        $this->assertSame(205, $logs->total(), 'O total deve refletir todos os eventos que batem com os filtros, não um corte fixo.');
        $this->assertSame(range(205, 156), $logs->pluck('id')->all());
        foreach ($logs as $log) {
            $this->assertTrue($log->relationLoaded('user'));
            $this->assertTrue($log->relationLoaded('empresa'));
        }
        $this->assertSame(50, substr_count($response->getContent(), '<li class="audit-event '));
        $response->assertViewHas('totalFiltered', 205);
        $this->assertDatabaseCount('audit_logs', 205);

        // Página 2 deve trazer o restante.
        $page2 = $this->get(route('auditoria.index', ['page' => 2]))->assertOk();
        $this->assertSame(range(155, 106), $page2->viewData('visibleLogs')->pluck('id')->all());
    }

    public function test_backend_filters_and_empty_state_remain_available(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (['auth.login', 'auth.login_failed', 'health.ok'] as $action) {
            AuditLog::create(['action' => $action, 'description' => 'Evento de teste', 'created_at' => now()]);
        }
        $this->actingAs($admin)->get(route('auditoria.index', ['q' => 'auth', 'bucket' => 'danger']))
            ->assertOk()->assertViewHas('totalFiltered', 1)->assertSeeText('Falha de login')
            ->assertDontSee('title="auth.login"', false)
            ->assertSee('name="q"', false)->assertSee('name="bucket"', false)->assertSeeText('Aplicar');
        $this->get(route('auditoria.index', ['q' => 'inexistente']))->assertOk()
            ->assertSeeText('Nenhum evento encontrado')->assertDontSee('<ul class="audit-stream"', false);
    }

    public function test_category_filter_narrows_results_at_the_database_level(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::create(['action' => 'anatel.import.completed', 'description' => 'Evento ANATEL', 'created_at' => now()]);
        AuditLog::create(['action' => 'servidor.created', 'description' => 'Evento servidor', 'created_at' => now()]);

        $response = $this->actingAs($admin)->get(route('auditoria.index', ['categoria' => 'anatel']))->assertOk();

        $response->assertViewHas('totalFiltered', 1)->assertSeeText('Importação de PDF/planilha ANATEL concluída');
        $response->assertDontSeeText('Servidor criado');
    }

    public function test_user_filter_including_system_events(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::create(['user_id' => $admin->id, 'action' => 'auth.login', 'description' => 'Login', 'created_at' => now()]);
        AuditLog::create(['user_id' => null, 'action' => 'health.ok', 'description' => 'Automático', 'created_at' => now()]);

        $this->actingAs($admin)->get(route('auditoria.index', ['usuario' => 'sistema']))
            ->assertOk()->assertViewHas('totalFiltered', 1)->assertSeeText('Sistema (automático)');
    }

    public function test_date_range_filter(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::create(['action' => 'auth.login', 'description' => 'Antigo', 'created_at' => now()->subDays(10)]);
        AuditLog::create(['action' => 'auth.login', 'description' => 'Recente', 'created_at' => now()]);

        $this->actingAs($admin)->get(route('auditoria.index', ['data_inicio' => now()->subDay()->toDateString()]))
            ->assertOk()->assertViewHas('totalFiltered', 1)->assertSeeText('Recente');
    }

    public function test_audit_route_remains_protected_for_guests_and_clients(): void
    {
        $route = app('router')->getRoutes()->getByName('auditoria.index');
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('admin', $route->gatherMiddleware());
        $this->get(route('auditoria.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->cliente()->create())->get(route('auditoria.index'))->assertForbidden();
    }
    public function test_manual_domain_event_shows_list_name_and_does_not_invent_an_automatic_actor(): void
    {
        $admin = User::factory()->admin()->create();
        $list = \App\Models\Lista::factory()->create(['nome' => 'Segurança manual']);
        AuditLog::create(['action' => 'dominio.created', 'target_type' => 'lista', 'target_id' => $list->id,
            'description' => 'Bloqueio manual autorizado: malware.example', 'created_at' => now()]);
        $this->actingAs($admin)->get(route('auditoria.index', ['categoria' => 'dominio']))
            ->assertOk()->assertViewHas('totalFiltered', 1)
            ->assertSeeText('Domínio adicionado ao bloqueio')->assertSeeText('Segurança manual')
            ->assertSeeText('Sem usuário identificado')->assertSeeText('Ver detalhes')
            ->assertDontSee('Endpoints (RPZ / Mikrotik)');
    }

    public function test_server_event_identifies_server_company_and_authenticated_actor(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Operador de teste']);
        $company = Empresa::factory()->create(['nome' => 'Provedor de teste']);
        $server = \App\Models\Servidor::factory()->for($company)->create(['nome' => 'DNS de teste']);
        AuditLog::create(['action' => 'servidor.lista_vinculada', 'target_type' => 'servidor', 'target_id' => $server->id,
            'description' => 'Lista de teste vinculada ao servidor', 'empresa_id' => $company->id, 'user_id' => $admin->id, 'created_at' => now()]);
        $this->actingAs($admin)->get(route('auditoria.index'))->assertOk()
            ->assertSeeText('Lista vinculada ao servidor')->assertSeeText('Operador de teste')
            ->assertSeeText('DNS de teste')->assertSeeText('Empresa: Provedor de teste');
    }

}
