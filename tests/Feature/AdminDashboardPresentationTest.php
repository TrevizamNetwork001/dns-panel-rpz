<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_uses_real_source_type_pluralization_and_pt_br_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        Servidor::factory()->for($empresa)->create(['last_synced_at' => now()]);
        $lista = Lista::factory()->anatel()->create();
        Lista::factory()->externa('anatel-proprio')->create(['nome' => 'Anatel externo']);
        Dominio::factory()->for($lista)->count(1001)->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Dashboard RPZ')
            ->assertSee('Catálogo')
            ->assertSee('1.001')
            ->assertSee('Endpoint ativo')
            ->assertSeeText('1 Normal')
            ->assertSeeInOrder(['ANATEL', 'Catálogo', 'Anatel externo', 'Externa'])
            ->assertDontSee('metric-sparkline');
    }

    public function test_admin_dashboard_translates_logout_and_describes_rpz_download_activity(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'auth.logout',
            'description' => "Logout de {$admin->email}",
            'created_at' => now()->subMinute(),
        ]);
        AuditLog::create([
            'action' => 'rpz.endpoint.downloaded',
            'description' => 'Endpoint RPZ empresarial baixado (244493 domínios)',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('<strong>Sessão encerrada</strong>', false)
            ->assertSee("Logout de {$admin->email}")
            ->assertDontSee('<strong>Logout</strong>', false)
            ->assertSee('<strong>Zona RPZ baixada</strong>', false)
            ->assertSee('Endpoint RPZ empresarial baixado (244.493 domínios)')
            ->assertDontSee('<strong>Auditoria</strong>', false);
    }

    public function test_admin_dashboard_separates_active_endpoint_count_from_operational_attention(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        Servidor::factory()->for($empresa)->create(['status' => 'active', 'last_synced_at' => now()]);
        Servidor::factory()->for($empresa)->count(2)->create(['status' => 'active', 'last_synced_at' => now()->subDays(3)]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('<span class="metric-label">Endpoints ativos</span>', false)
            ->assertSee('<div class="metric-value">3</div>', false)
            ->assertSee('2 com atenção operacional')
            ->assertDontSee('3 ativos')
            ->assertDontSee('2 em atenção')
            ->assertDontSee('<div class="metric-value">5</div>', false);
    }

    public function test_admin_dashboard_endpoint_card_uses_singular_and_normal_operation_copy(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        Servidor::factory()->for($empresa)->create(['status' => 'active', 'last_synced_at' => now()]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<span class="metric-label">Endpoint ativo</span>', false)
            ->assertSee('Todos operando normalmente');
    }

    public function test_admin_dashboard_limits_recent_activity_to_five_events(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (range(1, 7) as $index) {
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'lista.updated',
                'description' => "Evento dashboard {$index}",
                'created_at' => now()->subSeconds($index),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Evento dashboard 1')
            ->assertSee('Evento dashboard 5')
            ->assertDontSee('Evento dashboard 6')
            ->assertDontSee('Evento dashboard 7');

        $this->assertSame(5, substr_count($response->getContent(), 'activity-feed-item'));
    }

    public function test_admin_dashboard_recent_activity_has_a_bounded_query_count(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $servidores = Servidor::factory()->for($empresa)->count(10)->create();

        foreach ($servidores as $servidor) {
            $servidor->syncLogs()->create([
                'dominios_count' => 100,
                'created_at' => now(),
            ]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        $this->assertLessThan(25, $queries, 'Dashboard ADMIN não deve executar uma query por evento recente.');
    }

    public function test_client_dashboard_keeps_its_existing_heading_and_has_no_admin_wrapper(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();

        $this->actingAs($cliente)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Visão geral')
            ->assertDontSee('admin-dashboard')
            ->assertDontSee('Dashboard RPZ');
    }
}
