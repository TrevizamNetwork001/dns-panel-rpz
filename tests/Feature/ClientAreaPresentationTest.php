<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Licenca;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\SugestaoDominio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClientAreaPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cliente_dashboard_uses_simple_labels_pt_br_counts_and_operational_statuses(): void
    {
        Carbon::setTestNow('2026-08-27 12:00:00');
        $empresa = Empresa::factory()->create(['nome' => 'SPEEDNET']);
        $cliente = User::factory()->cliente($empresa)->create();
        Licenca::create([
            'empresa_id' => $empresa->id,
            'starts_at' => today()->subDay(),
            'expires_at' => today()->addDays(9),
            'max_servidores' => 2,
            'status' => 'active',
        ]);
        $lista = Lista::factory()->create(['empresa_id' => null, 'status' => 'active']);
        $servidorNormal = Servidor::factory()->for($empresa)->create(['last_synced_at' => now()->subHours(17)]);
        $servidorNormal->listas()->attach($lista);
        Dominio::factory()->count(1001)->for($lista)->create(['ativo' => true]);
        SugestaoDominio::create([
            'empresa_id' => $empresa->id,
            'dominio' => 'exemplo.com.br',
            'status' => 'pending',
            'created_by' => $cliente->id,
        ]);

        $response = $this->actingAs($cliente)->get('/');

        $response->assertOk()
            ->assertSeeInOrder(['Domínios bloqueados', 'Listas disponíveis', 'Servidor em uso', 'Servidores contratados'])
            ->assertSee('1.001')
            ->assertSee('usado da licença')
            ->assertSee('disponíveis')
            ->assertSee('há 17 h')
            ->assertSee('Normal')
            ->assertSee('Minhas sugestões de domínio')
            ->assertSee('+ Sugerir domínio')
            ->assertSee('Listas')
            ->assertSee('Servidores')
            ->assertDontSee('1 day ago')
            ->assertDontSee('Endpoints RPZ')
            ->assertDontSee('Fontes')
            ->assertDontSee('Exceções');
    }

    public function test_cliente_dashboard_distinguishes_attention_and_never_synchronized(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        Servidor::factory()->for($empresa)->create(['last_synced_at' => now()->subDays(2)]);
        Servidor::factory()->for($empresa)->create(['last_synced_at' => null]);

        $this->actingAs($cliente)->get('/')
            ->assertOk()
            ->assertSee('Atenção')
            ->assertSee('Sem sincronização');
    }

    public function test_cliente_only_sees_lists_and_servers_from_its_company_scope(): void
    {
        $empresa = Empresa::factory()->create();
        $outraEmpresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $listaPropria = Lista::factory()->create(['empresa_id' => $empresa->id, 'nome' => 'Lista própria']);
        $listaAlheia = Lista::factory()->create(['empresa_id' => $outraEmpresa->id, 'nome' => 'Lista alheia']);
        $servidorProprio = Servidor::factory()->for($empresa)->create(['nome' => 'Servidor próprio']);
        $servidorAlheio = Servidor::factory()->for($outraEmpresa)->create(['nome' => 'Servidor alheio']);

        $this->actingAs($cliente)->get('/listas')
            ->assertSee($listaPropria->nome)
            ->assertDontSee($listaAlheia->nome);
        $this->actingAs($cliente)->get('/servidores')
            ->assertSee($servidorProprio->nome)
            ->assertDontSee($servidorAlheio->nome);
        $this->actingAs($cliente)->get("/listas/{$listaAlheia->id}")->assertForbidden();
        $this->actingAs($cliente)->get("/servidores/{$servidorAlheio->id}")->assertForbidden();
    }
}
