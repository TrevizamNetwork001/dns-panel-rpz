<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ServerSyncLog;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteDashboardGraficoTest extends TestCase
{
    use RefreshDatabase;

    private function consulta(Servidor $servidor, \DateTimeInterface $quando): void
    {
        ServerSyncLog::create(['servidor_id' => $servidor->id, 'ip_address' => '127.0.0.1', 'dominios_count' => 10, 'created_at' => $quando]);
    }

    public function test_chart_counts_updates_per_day_and_fills_days_without_queries(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $a = Servidor::factory()->for($empresa)->create();
        $b = Servidor::factory()->for($empresa)->create();

        $this->consulta($a, now());
        $this->consulta($a, now());
        $this->consulta($b, now());
        $this->consulta($a, now()->subDays(3));

        $response = $this->actingAs($cliente)->get(route('dashboard'))->assertOk()
            ->assertSee('id="grafico-atualizacoes"', false)
            ->assertSeeText('Sincronizações por dia')
            ->assertSee('<polyline', false);

        $pontos = $response->viewData('graficoAtualizacoes');
        $this->assertCount(30, $pontos);
        $this->assertSame(3, $pontos[29]['total']);
        $this->assertSame(1, $pontos[26]['total']);
        $this->assertSame(0, $pontos[28]['total']);
        $this->assertSame(4, array_sum(array_column($pontos, 'total')));
        $this->assertSame(now()->toDateString(), $pontos[29]['data']->toDateString());
    }

    public function test_chart_ignores_other_companies_and_old_queries(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $meu = Servidor::factory()->for($empresa)->create();
        $alheio = Servidor::factory()->create();

        $this->consulta($alheio, now());
        $this->consulta($alheio, now()->subDays(2));
        $this->consulta($meu, now()->subDays(45));

        $response = $this->actingAs($cliente)->get(route('dashboard'))->assertOk();

        $this->assertSame(0, array_sum(array_column($response->viewData('graficoAtualizacoes'), 'total')));
    }

    public function test_empty_state_when_there_are_no_updates(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        Servidor::factory()->for($empresa)->create();

        $this->actingAs($cliente)->get(route('dashboard'))->assertOk()
            ->assertSeeText('Nenhuma atualização nos últimos 30 dias')
            ->assertDontSee('<polyline', false);
    }

    public function test_client_without_servers_still_renders(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();

        $this->actingAs($cliente)->get(route('dashboard'))->assertOk()
            ->assertSeeText('Nenhuma atualização nos últimos 30 dias');
    }

    public function test_admin_dashboard_does_not_show_the_client_chart(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertDontSee('id="grafico-atualizacoes"', false);
    }
}
