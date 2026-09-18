<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\SugestaoDominio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Um cliente nunca pode ler nem alterar dados de outra empresa, por nenhuma
 * das rotas que ele alcança (IDOR / parâmetros forjados).
 */
class ClienteIsolamentoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $a;

    private Empresa $b;

    private User $clienteA;

    private Servidor $srvA;

    private Servidor $srvB;

    private Lista $listaPrivadaA;

    private Lista $listaPrivadaB;

    private Lista $catalogo;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();

        $this->a = Empresa::factory()->create(['nome' => 'Empresa A']);
        $this->b = Empresa::factory()->create(['nome' => 'Empresa B Segredo']);
        $this->clienteA = User::factory()->cliente($this->a)->create();

        $this->srvA = Servidor::factory()->for($this->a)->create(['nome' => 'srv-a']);
        $this->srvB = Servidor::factory()->for($this->b)->create(['nome' => 'srv-b-segredo']);

        $this->listaPrivadaA = Lista::factory()->create(['empresa_id' => $this->a->id, 'status' => 'active', 'nome' => 'lista-privada-a']);
        $this->listaPrivadaB = Lista::factory()->create(['empresa_id' => $this->b->id, 'status' => 'active', 'nome' => 'lista-privada-b-segredo']);
        $this->catalogo = Lista::factory()->create(['empresa_id' => null, 'status' => 'active', 'nome' => 'catalogo-comum']);

        Dominio::factory()->for($this->listaPrivadaA)->create(['dominio' => 'so-da-a.example', 'ativo' => true]);
        Dominio::factory()->for($this->listaPrivadaB)->create(['dominio' => 'so-da-b-segredo.example', 'ativo' => true]);
        Dominio::factory()->for($this->catalogo)->create(['dominio' => 'de-todos.example', 'ativo' => true]);
        $this->srvB->listas()->attach($this->catalogo);
    }

    public function test_client_cannot_view_edit_update_or_delete_another_companys_server(): void
    {
        $this->actingAs($this->clienteA);

        $this->get(route('servidores.show', $this->srvB))->assertForbidden();
        $this->get(route('servidores.edit', $this->srvB))->assertForbidden();
        $this->put(route('servidores.update', $this->srvB), ['nome' => 'invadido', 'status' => 'active', 'tipo_dns' => 'unbound', 'bloqueio_modo' => 'nxdomain'])->assertForbidden();
        $this->delete(route('servidores.destroy', $this->srvB))->assertForbidden();

        $this->assertSame('srv-b-segredo', $this->srvB->fresh()->nome);
        $this->assertNotNull(Servidor::find($this->srvB->id));
    }

    public function test_client_cannot_attach_or_detach_lists_on_another_companys_server(): void
    {
        $this->actingAs($this->clienteA);

        $this->post(route('servidores.listas.attach', [$this->srvB, $this->catalogo]))->assertForbidden();
        $this->delete(route('servidores.listas.detach', [$this->srvB, $this->catalogo]))->assertForbidden();
        $this->assertTrue($this->srvB->listas()->whereKey($this->catalogo->id)->exists());
    }

    public function test_client_cannot_attach_another_companys_private_list_to_its_own_server(): void
    {
        $this->actingAs($this->clienteA)
            ->post(route('servidores.listas.attach', [$this->srvA, $this->listaPrivadaB]))->assertForbidden();

        $this->assertFalse($this->srvA->listas()->whereKey($this->listaPrivadaB->id)->exists());
    }

    public function test_forged_company_and_list_ids_are_ignored_or_rejected_on_create_and_update(): void
    {
        $this->actingAs($this->clienteA);
        $this->a->licencas()->create(['status' => 'active', 'max_servidores' => 9, 'starts_at' => now()->subDay(), 'expires_at' => null]);

        $this->post(route('servidores.store'), [
            'nome' => 'forjado', 'status' => 'active', 'tipo_dns' => 'unbound', 'bloqueio_modo' => 'nxdomain',
            'ip_v4' => '203.0.113.5', 'empresa_id' => $this->b->id, 'lista_ids' => [$this->listaPrivadaB->id],
        ])->assertSessionHasErrors('lista_ids.0');
        $this->assertSame(0, Servidor::where('nome', 'forjado')->count());

        $this->post(route('servidores.store'), [
            'nome' => 'meu-novo', 'status' => 'active', 'tipo_dns' => 'unbound', 'bloqueio_modo' => 'nxdomain',
            'ip_v4' => '203.0.113.6', 'empresa_id' => $this->b->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->a->id, Servidor::where('nome', 'meu-novo')->value('empresa_id'), 'empresa_id enviado pelo cliente deve ser ignorado.');

        $this->put(route('servidores.update', $this->srvA), [
            'nome' => 'srv-a', 'status' => 'active', 'tipo_dns' => 'unbound', 'bloqueio_modo' => 'nxdomain', 'empresa_id' => $this->b->id,
        ])->assertRedirect();
        $this->assertSame($this->a->id, $this->srvA->fresh()->empresa_id);
    }

    public function test_client_cannot_open_another_company_or_its_private_lists(): void
    {
        $this->actingAs($this->clienteA);

        $this->get(route('empresas.show', $this->b))->assertForbidden();
        $this->get(route('empresas.show', $this->a))->assertOk();
        $this->get(route('listas.show', $this->listaPrivadaB))->assertForbidden();
        $this->get(route('listas.historico', $this->listaPrivadaB))->assertForbidden();
        $this->get(route('listas.show', $this->listaPrivadaA))->assertOk();
        $this->get(route('listas.show', $this->catalogo))->assertOk();
    }

    public function test_lists_index_and_dashboard_never_reveal_another_companys_data(): void
    {
        $this->actingAs($this->clienteA);

        foreach ([route('listas.index'), route('dashboard'), route('servidores.index'), route('dominios.index')] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSeeText('lista-privada-b-segredo')
                ->assertDontSeeText('Empresa B Segredo')
                ->assertDontSeeText('srv-b-segredo')
                ->assertDontSeeText('so-da-b-segredo.example');
        }
    }

    public function test_domain_search_and_filters_only_see_catalog_and_own_lists(): void
    {
        $this->actingAs($this->clienteA);

        $this->get(route('dominios.index', ['dominio' => 'so-da-b-segredo']))->assertOk()
            ->assertDontSeeText('so-da-b-segredo.example')->assertDontSeeText('lista-privada-b-segredo');
        $this->get(route('dominios.index', ['fonte_id' => $this->listaPrivadaB->id]))->assertOk()
            ->assertDontSeeText('so-da-b-segredo.example');
        $this->get(route('dominios.index', ['dominio' => 'so-da-a']))->assertOk()->assertSeeText('so-da-a.example');
        $this->get(route('dominios.index', ['dominio' => 'de-todos']))->assertOk()->assertSeeText('de-todos.example');

        $this->get(route('consulta.index', ['dominio' => 'so-da-b-segredo.example']))->assertOk()
            ->assertDontSeeText('lista-privada-b-segredo')->assertDontSeeText('Empresa B Segredo');
        $this->get(route('consulta.index', ['dominio' => 'segredo']))->assertOk()
            ->assertDontSeeText('so-da-b-segredo.example');
    }

    public function test_domain_counters_do_not_count_other_companys_private_domains(): void
    {
        $response = $this->actingAs($this->clienteA)->get(route('dominios.index'))->assertOk();

        $this->assertSame(2, $response->viewData('contadores')['total'], 'Só catálogo + lista própria (1 + 1).');
    }

    public function test_suggestions_are_scoped_to_the_own_company_and_forged_company_is_ignored(): void
    {
        SugestaoDominio::create(['empresa_id' => $this->b->id, 'dominio' => 'sugestao-da-b.example', 'status' => 'pending', 'created_by' => User::factory()->cliente($this->b)->create()->id]);
        $this->actingAs($this->clienteA);

        $this->get(route('sugestoes.index'))->assertOk()->assertDontSeeText('sugestao-da-b.example');

        $this->post(route('sugestoes.store'), ['dominio' => 'minha-sugestao.example', 'empresa_id' => $this->b->id])->assertRedirect();
        $this->assertSame($this->a->id, SugestaoDominio::where('dominio', 'minha-sugestao.example')->value('empresa_id'));
    }

    public function test_admin_only_routes_are_forbidden_for_clients_and_redirect_guests(): void
    {
        $somenteAdmin = [
            ['get', route('empresas.index')], ['get', route('licencas.index')], ['get', route('usuarios.index')],
            ['get', route('auditoria.index')], ['get', route('configuracoes.index')], ['get', route('seguranca.index')],
            ['get', route('anatel.dashboard')], ['get', route('rbl.index')], ['get', route('listas.create')],
            ['get', route('listas.edit', $this->catalogo)], ['get', route('listas.dominios.index', $this->listaPrivadaA)],
            ['get', route('anatel.exclusions', Lista::factory()->anatel()->create())], ['get', route('listas.rpz-preview', $this->catalogo)],
            ['post', route('listas.dominios.store', $this->listaPrivadaA)], ['post', route('servidores.ips.store', $this->srvA)],
            ['post', route('servidores.ip-restriction.toggle', $this->srvA)], ['post', route('listas.sync-now', $this->catalogo)],
            ['delete', route('listas.destroy', $this->listaPrivadaA)],
        ];

        foreach ($somenteAdmin as [$metodo, $url]) {
            $this->actingAs($this->clienteA)->{$metodo}($url)->assertForbidden();
        }

        auth()->logout();
        foreach ($somenteAdmin as [$metodo, $url]) {
            $this->{$metodo}($url)->assertRedirect(route('login'));
        }
    }

    public function test_client_cannot_release_ips_or_approve_suggestions(): void
    {
        $ip = $this->srvA->allowedIps()->create(['ip_cidr' => '203.0.113.7', 'status' => 'pending']);
        $sugestao = SugestaoDominio::create(['empresa_id' => $this->a->id, 'dominio' => 'x.example', 'status' => 'pending', 'created_by' => $this->clienteA->id]);

        $this->actingAs($this->clienteA)->patch(route('servidores.ips.approve', [$this->srvA, $ip]))->assertForbidden();
        $this->post(route('sugestoes.aprovar', $sugestao), ['lista_id' => $this->catalogo->id])->assertForbidden();

        $this->assertSame('pending', $ip->fresh()->status);
        $this->assertSame('pending', $sugestao->fresh()->status);
    }
}
