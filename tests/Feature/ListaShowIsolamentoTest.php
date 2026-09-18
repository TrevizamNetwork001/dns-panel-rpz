<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaShowIsolamentoTest extends TestCase
{
    use RefreshDatabase;

    private function catalogoComDoisClientes(): array
    {
        $minha = Empresa::factory()->create(['nome' => 'Minha Empresa Ltda']);
        $outra = Empresa::factory()->create(['nome' => 'Empresa Concorrente SA']);
        $lista = Lista::factory()->anatel()->create(['empresa_id' => null, 'status' => 'active', 'nome' => 'ANATEL — Bloqueios Oficiais']);
        $meu = Servidor::factory()->for($minha)->create(['nome' => 'meu-dns']);
        $alheio = Servidor::factory()->for($outra)->create(['nome' => 'dns-do-concorrente']);
        $lista->servidores()->attach([$meu->id, $alheio->id]);

        return [$minha, $lista, User::factory()->cliente($minha)->create()];
    }

    public function test_client_only_sees_its_own_servers_on_a_catalog_list(): void
    {
        [, $lista, $cliente] = $this->catalogoComDoisClientes();

        $this->actingAs($cliente)->get(route('listas.show', $lista))->assertOk()
            ->assertSeeText('Meus servidores que usam esta lista')
            ->assertSeeText('meu-dns')
            ->assertDontSeeText('dns-do-concorrente')
            ->assertDontSeeText('Empresa Concorrente SA');
    }

    public function test_client_does_not_get_admin_only_anatel_buttons(): void
    {
        [, $lista, $cliente] = $this->catalogoComDoisClientes();

        $this->actingAs($cliente)->get(route('listas.show', $lista))->assertOk()
            ->assertDontSee(route('anatel.exclusions', $lista), false)
            ->assertDontSee(route('anatel.history', $lista), false)
            ->assertSee(route('listas.historico', $lista), false);
    }

    public function test_admin_still_sees_every_server_with_its_company(): void
    {
        [, $lista] = $this->catalogoComDoisClientes();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('listas.show', $lista))->assertOk()
            ->assertSeeText('Endpoints RPZ vinculados')
            ->assertSeeText('meu-dns')->assertSeeText('dns-do-concorrente')
            ->assertSeeText('Empresa Concorrente SA')
            ->assertSee(route('anatel.exclusions', $lista), false);
    }

    public function test_client_with_no_server_on_the_list_gets_an_empty_state(): void
    {
        $cliente = User::factory()->cliente(Empresa::factory()->create())->create();
        $lista = Lista::factory()->create(['empresa_id' => null, 'status' => 'active']);
        $outro = Servidor::factory()->create(['nome' => 'servidor-de-terceiros']);
        $lista->servidores()->attach($outro);

        $this->actingAs($cliente)->get(route('listas.show', $lista))->assertOk()
            ->assertSeeText('Nenhum dos seus servidores usa esta lista ainda.')
            ->assertDontSeeText('servidor-de-terceiros');
    }
}
