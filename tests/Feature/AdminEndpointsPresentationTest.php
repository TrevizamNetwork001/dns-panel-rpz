<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminEndpointsPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_listing_shows_relative_consultation_operational_and_registration_statuses(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();

        Servidor::factory()->for($empresa)->create(['nome' => 'normal-rpz', 'last_synced_at' => now(), 'status' => 'active']);
        Servidor::factory()->for($empresa)->create(['nome' => 'atencao-rpz', 'last_synced_at' => now()->subDays(3), 'status' => 'active']);
        Servidor::factory()->for($empresa)->inactive()->create(['nome' => 'sem-consulta-rpz', 'last_synced_at' => null]);

        $response = $this->actingAs($admin)->get(route('servidores.index'));

        $response->assertOk()
            ->assertSee('agora mesmo')
            ->assertSee('há 3 dias')
            ->assertSee('Normal')
            ->assertSee('Atenção')
            ->assertSee('Sem consulta')
            ->assertSee('Ativo no painel')
            ->assertSee('Inativo no painel')
            ->assertSee('Ver detalhes')
            ->assertSee('actions-menu-item-danger');
    }

    public function test_admin_form_has_supported_dns_types_secure_access_controls_and_real_source_types(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $servidor = Servidor::factory()->for($empresa)->create(['tipo_dns' => 'unbound']);
        Lista::factory()->anatel()->create(['nome' => 'ANATEL']);
        Lista::factory()->externa('anatel-proprio')->create(['nome' => 'Anatel']);

        $response = $this->actingAs($admin)->get(route('servidores.edit', $servidor));

        $response->assertOk()
            ->assertSee('name="tipo_dns"', false)
            ->assertSee('<option value="unbound" selected>Unbound (RPZ)</option>', false)
            ->assertSee('MikroTik RouterOS v7 (Adlist)')
            ->assertDontSee('BIND9 (em breve)')
            ->assertSee('id="rpz-url-value" type="text"', false)
            ->assertSee('readonly', false)
            ->assertSee('••••••••••••••••••••••••••••')
            ->assertSee('id="token-value"', false)
            ->assertDontSee('id="token-masked"', false)
            ->assertSee('Mostrar')
            ->assertSee('Copiar')
            ->assertSee('Selecionar todas')
            ->assertSee('Limpar seleção')
            ->assertSee('Catálogo / Importação ANATEL')
            ->assertSeeInOrder(['ANATEL', 'Catálogo / Importação ANATEL', 'Anatel', 'Externa'])
            ->assertDontSee('Estável')
            ->assertSee('marcadas === 1 ? \'selecionada\' : \'selecionadas\'', false);

        $this->assertSame(1, substr_count($response->getContent(), 'id="token-value"'));
    }

    public function test_admin_detail_prioritizes_operational_state_access_and_masked_config(): void
    {
        $admin = User::factory()->admin()->create();
        $servidor = Servidor::factory()->create(['last_synced_at' => null]);

        $response = $this->actingAs($admin)->get(route('servidores.show', $servidor));

        $response->assertOk()
            ->assertSee('Status cadastral')
            ->assertSee('Estado operacional')
            ->assertSee('Última consulta')
            ->assertDontSee('Acesso RPZ')
            ->assertDontSee('Fontes disponíveis para adicionar')
            ->assertSee('Ver listas')
            ->assertSee('Sincronizar agora')
            ->assertSee('Solução de problemas')
            ->assertSee('Ver histórico completo')
            ->assertSee('detail-rpz-url')
            ->assertDontSee('detail-token-value')
            ->assertDontSee('detail-token-masked')
            ->assertSee('Configuração do Unbound');

        $response->assertDontSee('detailEndpointToken');
    }

    public function test_admin_detail_prioritizes_short_company_url_without_legacy_panel(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create(['rpz_slug' => 'speed-fiber']);
        $servidor = Servidor::factory()->for($empresa)->create();
        $shortUrl = url('/rpz/speed-fiber.zone');
        $legacyUrl = url('/rpz/'.$servidor->token.'.zone');

        $response = $this->actingAs($admin)->get(route('servidores.show', $servidor));

        $response->assertOk()
            ->assertSee('Método')
            ->assertSee('ACL por IP')
            ->assertSee('URL curta')
            ->assertSee('zonefile: &quot;/var/lib/unbound/'.parse_url(config('app.url'), PHP_URL_HOST).'.zone&quot;', false)
            ->assertSee('url: &quot;'.$shortUrl.'&quot;', false)
            ->assertDontSee('Acesso legado por token')
            ->assertDontSee('id="legacy-rpz-url"', false)
            ->assertDontSee($legacyUrl)
            ->assertSeeInOrder(['id="detail-rpz-url"', $shortUrl], false)
            ->assertDontSee($servidor->token);

        $this->assertStringNotContainsString($servidor->token, $this->mainConfig($response->getContent()));
        $this->assertStringContainsString($shortUrl, $this->copiedConfig($response->getContent()));
    }

    public function test_company_without_slug_falls_back_to_legacy_url_without_error(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $empresa->update(['rpz_slug' => null]);
        $servidor = Servidor::factory()->for($empresa)->create();

        $this->actingAs($admin)->get(route('servidores.show', $servidor))
            ->assertOk()
            ->assertSee('Token legado (fallback)')
            ->assertSee(url('/rpz/'.$servidor->token.'.zone'));
    }

    public function test_endpoint_form_rejects_a_source_from_another_company(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $outraEmpresa = Empresa::factory()->create();
        $servidor = Servidor::factory()->for($empresa)->create();
        $fonteAlheia = Lista::factory()->create(['empresa_id' => $outraEmpresa->id]);

        $this->actingAs($admin)->put(route('servidores.update', $servidor), [
            'empresa_id' => $empresa->id,
            'nome' => $servidor->nome,
            'status' => 'active',
            'tipo_dns' => 'unbound',
            'bloqueio_modo' => 'nxdomain',
            'lista_ids' => [$fonteAlheia->id],
        ])->assertSessionHasErrors('lista_ids.0');

        $this->assertFalse($servidor->listas()->whereKey($fonteAlheia->id)->exists());
    }

    public function test_endpoint_listing_keeps_a_bounded_query_count(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $lista = Lista::factory()->create();

        Servidor::factory()->for($empresa)->count(20)->create()->each(fn (Servidor $servidor) => $servidor->listas()->attach($lista));

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->actingAs($admin)->get(route('servidores.index'))->assertOk();

        $this->assertLessThan(15, $queries, 'A listagem deve carregar empresas e fontes com eager loading.');
    }

    public function test_client_can_choose_supported_dns_type_and_does_not_receive_admin_access_panel(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = User::factory()->cliente($empresa)->create();
        $servidor = Servidor::factory()->for($empresa)->create();

        $this->actingAs($cliente)->get(route('servidores.edit', $servidor))
            ->assertOk()
            ->assertSee('name="tipo_dns"', false)
            ->assertSee('<option value="unbound" selected>Unbound (RPZ)</option>', false)
            ->assertSee('MikroTik RouterOS v7 (Adlist)')
            ->assertDontSee('BIND9 (em breve)')
            ->assertDontSee('Estável')
            ->assertDontSee('Selecionar todas');

        $this->actingAs($cliente)->get(route('servidores.show', $servidor))
            ->assertOk()
            ->assertDontSee('admin-endpoint-access')
            ->assertSee('Status cadastral')
            ->assertSee('Estado operacional')
            ->assertSee('endpoint-detail-statuses')
            ->assertDontSee('server-status-pill')
            ->assertDontSee('id="endpoint-ips"', false);
    }

    public function test_unbound_server_page_shows_module_config_step_but_mikrotik_does_not(): void
    {
        $admin = User::factory()->admin()->create();
        $empresa = Empresa::factory()->create();
        $unbound = Servidor::factory()->for($empresa)->create(['tipo_dns' => 'unbound']);
        $mikrotik = Servidor::factory()->for($empresa)->create(['tipo_dns' => 'mikrotik']);

        $this->actingAs($admin)->get(route('servidores.show', $unbound))->assertOk()
            ->assertSee('id="module-snippet"', false)
            ->assertSee('module-config: "respip validator iterator"', false)
            ->assertSeeText('Passo 1')->assertSeeText('Passo 2')
            ->assertSeeText('A ordem importa')
            ->assertSee('id="rpz-fields-help"', false)
            ->assertSeeText('Nome da zona no Unbound')
            ->assertSeeText('Nome que identifica as linhas de log deste RPZ')
            ->assertSee('id="rpz-attention"', false)
            ->assertSee('id="rpz-restart-steps"', false)
            ->assertSeeText('Passo 3')->assertSeeText('Passo 4')
            ->assertSee('unbound-checkconf', false)
            ->assertSee('systemctl restart unbound', false)
            ->assertSee('dig @127.0.0.1 blocktest.', false)
            ->assertSeeText('Não deixe o log habilitado em produção');

        $config = $this->mainConfig($this->get(route('servidores.show', $unbound))->getContent());
        $this->assertStringContainsString('# rpz-log: yes', $config);
        $this->assertDoesNotMatchRegularExpression('/^\s*rpz-log:/m', $config, 'O log do RPZ deve vir comentado por padrão.');

        $this->get(route('servidores.show', $mikrotik))->assertOk()
            ->assertDontSee('id="module-snippet"', false)
            ->assertDontSee('id="rpz-fields-help"', false)
            ->assertDontSee('id="rpz-restart-steps"', false)
            ->assertDontSee('module-config');
    }

    private function mainConfig(string $content): string
    {
        preg_match('/<pre[^>]+id="config-snippet"[^>]*>(.*?)<\/pre>/s', $content, $matches);

        return html_entity_decode($matches[1] ?? '');
    }

    private function copiedConfig(string $content): string
    {
        preg_match('/var endpointConfigSnippet = (".*?");/s', $content, $matches);

        return json_decode($matches[1] ?? '""', true) ?: '';
    }
}
