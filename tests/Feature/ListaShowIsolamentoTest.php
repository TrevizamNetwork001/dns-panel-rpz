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

    public function test_client_does_not_get_the_history_button_nor_the_anatel_pdf_panel(): void
    {
        [, $lista, $cliente] = $this->catalogoComDoisClientes();

        $this->actingAs($cliente)->get(route('listas.show', $lista))->assertOk()
            ->assertDontSee(route('anatel.exclusions', $lista), false)
            ->assertDontSee(route('anatel.history', $lista), false)
            ->assertDontSee('href="'.route('listas.historico', $lista).'"', false)
            ->assertDontSeeText('ANATEL / PDF')
            ->assertDontSeeText('Fonte oficial')
            ->assertDontSeeText('A importação é incremental');
    }

    public function test_admin_still_sees_every_server_with_its_company(): void
    {
        [, $lista] = $this->catalogoComDoisClientes();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('listas.show', $lista))->assertOk()
            ->assertSeeText('Endpoints RPZ vinculados')
            ->assertSeeText('meu-dns')->assertSeeText('dns-do-concorrente')
            ->assertSeeText('Empresa Concorrente SA')
            ->assertSee(route('anatel.exclusions', $lista), false)
            ->assertSee('href="'.route('listas.historico', $lista).'"', false)
            ->assertSeeText('ANATEL / PDF');
    }

    private function listaExterna(): Lista
    {
        return Lista::factory()->externa('threatfox')->create([
            'empresa_id' => null, 'status' => 'active', 'nome' => 'Ameaças Gerais',
            'fonte_url' => 'https://feeds.exemplo.test/segredo/lista.txt',
            'descricao' => 'Lista ampla de ameaças.',
        ]);
    }

    public function test_client_never_sees_how_an_external_list_is_fed(): void
    {
        $lista = $this->listaExterna();
        $cliente = User::factory()->cliente(Empresa::factory()->create())->create();

        $response = $this->actingAs($cliente)->get(route('listas.show', $lista))->assertOk()
            ->assertSeeText('Ameaças Gerais')
            ->assertDontSeeText('Sincronização automática')
            ->assertDontSeeText('Última sincronização')
            ->assertDontSeeText('feed externo')
            ->assertDontSeeText('fonte externa')
            ->assertDontSeeText('Externa');

        $this->assertStringNotContainsString('feeds.exemplo.test', $response->getContent(), 'A URL do feed não pode aparecer para o cliente.');
    }

    public function test_admin_still_sees_the_sync_panel_and_feed_url(): void
    {
        $lista = $this->listaExterna();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('listas.show', $lista))->assertOk()
            ->assertSeeText('Sincronização automática')->assertSeeText('Última sincronização')
            ->assertSee('feeds.exemplo.test', false);
    }

    public function test_lists_index_and_server_form_do_not_label_lists_as_external_for_clients(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $servidor = Servidor::factory()->for($empresa)->create();
        $this->listaExterna();

        $this->actingAs($cliente)->get(route('listas.index'))->assertOk()
            ->assertSeeText('Ameaças Gerais')->assertSeeText('Catálogo')->assertDontSeeText('Externa');
        $this->get(route('servidores.edit', $servidor))->assertOk()
            ->assertSeeText('Ameaças Gerais')->assertDontSeeText('Externa');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('listas.index'))->assertOk()->assertSeeText('Externa');
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
