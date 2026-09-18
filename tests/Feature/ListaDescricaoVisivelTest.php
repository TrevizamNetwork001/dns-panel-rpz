<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaDescricaoVisivelTest extends TestCase
{
    use RefreshDatabase;

    private function contexto(): array
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $servidor = Servidor::factory()->for($empresa)->create();

        return [$empresa, $cliente, $servidor];
    }

    public function test_client_dashboard_shows_what_each_list_is_for(): void
    {
        [, $cliente] = $this->contexto();
        Lista::factory()->create(['empresa_id' => null, 'status' => 'active', 'nome' => 'Phishing e Golpes', 'descricao' => 'Falsos sites de banco criados para roubar dados.']);
        Lista::factory()->create(['empresa_id' => null, 'status' => 'active', 'nome' => 'Lista sem texto', 'descricao' => null]);

        $this->actingAs($cliente)->get(route('dashboard'))->assertOk()
            ->assertSeeText('Phishing e Golpes')
            ->assertSeeText('Falsos sites de banco criados para roubar dados.')
            ->assertSeeText('Lista sem texto');
    }

    public function test_server_form_shows_description_and_makes_it_searchable(): void
    {
        [, $cliente, $servidor] = $this->contexto();
        Lista::factory()->create(['empresa_id' => null, 'status' => 'active', 'nome' => 'Ransomware', 'descricao' => 'Domínios ligados a sequestro de dados.']);

        $response = $this->actingAs($cliente)->get(route('servidores.edit', $servidor))->assertOk()
            ->assertSeeText('Domínios ligados a sequestro de dados.');

        $this->assertStringContainsString('data-fonte-nome="ransomware domínios ligados a sequestro de dados."', $response->getContent());
    }

    public function test_server_page_and_list_index_show_the_description(): void
    {
        [, $cliente, $servidor] = $this->contexto();
        $lista = Lista::factory()->create(['empresa_id' => null, 'status' => 'active', 'nome' => 'Malware e Vírus', 'descricao' => 'Sites que distribuem vírus.']);
        $servidorVinculado = Servidor::factory()->for($servidor->empresa)->create();
        $servidorVinculado->listas()->attach($lista);

        $this->actingAs($cliente)->get(route('servidores.show', $servidor))->assertOk()->assertSeeText('Sites que distribuem vírus.');
        $this->get(route('servidores.show', $servidorVinculado))->assertOk()->assertSeeText('Sites que distribuem vírus.');
        $this->get(route('listas.index'))->assertOk()->assertSeeText('Sites que distribuem vírus.');
    }
}
