<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSeeText('1 ativo')
            ->assertSee('Endpoints RPZ')
            ->assertSeeText('1 Normal')
            ->assertSeeInOrder(['ANATEL', 'Catálogo', 'Anatel externo', 'Externa'])
            ->assertDontSee('metric-sparkline');
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
