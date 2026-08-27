<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDomainsPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_domains_use_client_language_and_compact_pagination_with_filters(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $lista = Lista::factory()->create(['empresa_id' => $empresa->id, 'nome' => 'Lista Cliente']);

        foreach (range(1, 51) as $index) {
            Dominio::factory()->for($lista)->create([
                'dominio' => sprintf('cliente-%03d.example', $index),
                'ativo' => true,
            ]);
        }

        $response = $this->actingAs($cliente)->get(route('dominios.index', [
            'dominio' => 'cliente-',
            'status' => 'ativos',
            'fonte_id' => $lista->id,
        ]));

        $response->assertOk()
            ->assertSee('Pesquise e consulte os domínios distribuídos pelas listas da sua empresa.')
            ->assertSee('Todas as listas')
            ->assertSee('Ver lista')
            ->assertSee('Anterior')
            ->assertSee('Página 1')
            ->assertSee('Próxima')
            ->assertSee('dominio=cliente-&amp;status=ativos&amp;fonte_id='.$lista->id.'&amp;page=2', false)
            ->assertDontSee('Previous')
            ->assertDontSee('Next')
            ->assertDontSee('Ver fonte');

        $this->assertCount(50, $response->viewData('dominios')->items());
    }

    public function test_client_domains_are_scoped_to_its_company_and_catalog_lists(): void
    {
        $empresa = Empresa::factory()->create();
        $outraEmpresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $listaPropria = Lista::factory()->create(['empresa_id' => $empresa->id]);
        $listaCatalogo = Lista::factory()->create(['empresa_id' => null]);
        $listaAlheia = Lista::factory()->create(['empresa_id' => $outraEmpresa->id, 'nome' => 'Lista de outra empresa']);

        Dominio::factory()->for($listaPropria)->create(['dominio' => 'proprio.example']);
        Dominio::factory()->for($listaCatalogo)->create(['dominio' => 'catalogo.example']);
        Dominio::factory()->for($listaAlheia)->create(['dominio' => 'alheio.example']);

        $this->actingAs($cliente)->get(route('dominios.index'))
            ->assertOk()
            ->assertSee('proprio.example')
            ->assertSee('catalogo.example')
            ->assertDontSee('alheio.example')
            ->assertDontSee('Lista de outra empresa');

        $this->actingAs($cliente)->get(route('listas.show', $listaAlheia))->assertForbidden();
    }
}
