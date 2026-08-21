<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_history(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();

        $this->actingAs($admin)->get(route('listas.historico', $lista))->assertOk();
    }

    public function test_cliente_cannot_view_history_of_another_empresas_private_lista(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresaA)->create();
        $listaAlheia = Lista::factory()->create(['empresa_id' => $empresaB->id]);

        $this->actingAs($cliente)->get(route('listas.historico', $listaAlheia))->assertStatus(403);
    }

    public function test_cliente_can_view_history_of_catalog_lista(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $catalogo = Lista::factory()->create(['empresa_id' => null]);

        $this->actingAs($cliente)->get(route('listas.historico', $catalogo))->assertOk();
    }

    public function test_shows_domains_added_today(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();

        $recente = Dominio::factory()->for($lista)->create(['dominio' => 'novo-hoje.example']);
        $recente->forceFill(['created_at' => now()])->saveQuietly();

        $antigo = Dominio::factory()->for($lista)->create(['dominio' => 'antigo.example']);
        $antigo->forceFill(['created_at' => now()->subDays(5)])->saveQuietly();

        $response = $this->actingAs($admin)->get(route('listas.historico', $lista));

        $response->assertOk();
        $response->assertSee('novo-hoje.example');
        $response->assertDontSee('antigo.example');
    }

    public function test_shows_domains_removed_today(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();

        $removidoHoje = Dominio::factory()->for($lista)->inativo()->create(['dominio' => 'removido-hoje.example']);
        $removidoHoje->forceFill(['updated_at' => now()])->saveQuietly();

        $response = $this->actingAs($admin)->get(route('listas.historico', $lista));

        $response->assertOk();
        $response->assertSee('removido-hoje.example');
    }

    public function test_period_filter_expands_the_window(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();

        $dominio = Dominio::factory()->for($lista)->create(['dominio' => 'ha-5-dias.example']);
        $dominio->forceFill(['created_at' => now()->subDays(5)])->saveQuietly();

        $hoje = $this->actingAs($admin)->get(route('listas.historico', [$lista, 'periodo' => 'hoje']));
        $hoje->assertDontSee('ha-5-dias.example');

        $seteDias = $this->actingAs($admin)->get(route('listas.historico', [$lista, 'periodo' => '7dias']));
        $seteDias->assertSee('ha-5-dias.example');
    }

    public function test_hides_detailed_table_when_too_many_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $lista = Lista::factory()->create();

        $agora = now();
        $rows = [];
        for ($i = 0; $i < 250; $i++) {
            $rows[] = [
                'lista_id' => $lista->id,
                'dominio' => "muitos-{$i}.example",
                'ativo' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }
        \Illuminate\Support\Facades\DB::table('dominios')->insert($rows);

        $response = $this->actingAs($admin)->get(route('listas.historico', $lista));

        $response->assertOk();
        $response->assertSee('mostrando só o resumo');
        $response->assertDontSee('muitos-0.example');
    }
}
