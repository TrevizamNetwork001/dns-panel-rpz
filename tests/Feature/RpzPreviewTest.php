<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\User;
use App\Services\RpzZoneBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RpzPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Lista $lista;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->lista = Lista::factory()->create(['nome' => 'ANATEL', 'origem' => 'anatel']);
        config(['app.url' => 'http://localhost']);
        Carbon::setTestNow('2026-09-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_access_preview_and_empty_list_works(): void
    {
        $this->actingAs($this->admin)->get(route('listas.rpz-preview', $this->lista))
            ->assertOk()->assertSee('Preview RPZ')->assertSee('PRONTO')->assertSee('Entradas finais');
    }

    public function test_non_admin_gets_403_and_does_not_see_button(): void
    {
        $client = User::factory()->cliente()->create();
        $this->actingAs($client)->get(route('listas.rpz-preview', $this->lista))->assertForbidden();
        $this->actingAs($client)->get(route('listas.show', $this->lista))->assertOk()->assertDontSee('Preview RPZ');
    }

    public function test_active_domain_appears_but_inactive_and_excluded_do_not(): void
    {
        Dominio::factory()->for($this->lista)->create(['dominio' => 'ativo.example']);
        Dominio::factory()->for($this->lista)->inativo()->create(['dominio' => 'inativo.example']);
        Dominio::factory()->for($this->lista)->inativo()->create(['dominio' => 'excluido.example', 'inactive_reason' => 'anatel_exclusion']);
        $response = $this->actingAs($this->admin)->get(route('listas.rpz-preview', $this->lista));
        $response->assertSee('ativo.example CNAME .')->assertDontSee('inativo.example CNAME')->assertDontSee('excluido.example CNAME');
    }

    public function test_exact_exclusion_does_not_affect_similar_domain(): void
    {
        Dominio::factory()->for($this->lista)->inativo()->create(['dominio' => 'xplus.biz', 'inactive_reason' => 'anatel_exclusion']);
        Dominio::factory()->for($this->lista)->create(['dominio' => 'hypeflixplus.biz']);
        $response = $this->actingAs($this->admin)->get(route('listas.rpz-preview', $this->lista));
        $response->assertSee('hypeflixplus.biz CNAME .');
        $content = app(RpzZoneBuilder::class)->build(app(RpzZoneBuilder::class)->listQuery($this->lista), '.', '123');
        $this->assertDoesNotMatchRegularExpression('/^xplus\.biz CNAME/m', $content);
    }

    public function test_regex_excluded_domain_is_not_rendered(): void
    {
        Dominio::factory()->for($this->lista)->inativo()->create(['dominio' => 'regex.example', 'inactive_reason' => 'anatel_exclusion']);
        $this->actingAs($this->admin)->get(route('listas.rpz-preview', $this->lista))->assertDontSee('regex.example CNAME');
    }

    public function test_builder_deduplicates_and_orders_deterministically(): void
    {
        Dominio::factory()->for($this->lista)->create(['dominio' => 'zeta.example']);
        Dominio::factory()->for($this->lista)->create(['dominio' => 'Alpha.example']);
        Dominio::factory()->for($this->lista)->create(['dominio' => 'alpha.example']);
        $content = app(RpzZoneBuilder::class)->build(app(RpzZoneBuilder::class)->listQuery($this->lista), '.', '123');
        $this->assertSame(1, preg_match_all('/^alpha\.example CNAME \\.$/m', $content));
        $this->assertLessThan(strpos($content, 'zeta.example CNAME .'), strpos($content, 'alpha.example CNAME .'));
    }

    public function test_preview_respects_500_rule_limit_and_reports_truncation(): void
    {
        $now = now();
        for ($i = 0; $i < 260; $i++) DB::table('dominios')->insert(['lista_id' => $this->lista->id, 'dominio' => sprintf('d%03d.example', $i), 'ativo' => true, 'created_at' => $now, 'updated_at' => $now]);
        $response = $this->actingAs($this->admin)->get(route('listas.rpz-preview', $this->lista));
        $response->assertOk()->assertSee('primeiras 500 regras')->assertSee('d249.example CNAME .')->assertDontSee('d250.example CNAME .');
    }

    public function test_download_is_complete_and_admin_only(): void
    {
        Dominio::factory()->for($this->lista)->create(['dominio' => 'completo.example']);
        $response = $this->actingAs($this->admin)->get(route('listas.rpz-preview.download', $this->lista));
        $response->assertOk()->assertDownload('anatel-rpz-preview-20260901.zone');
        $this->assertStringContainsString('completo.example CNAME .', $response->streamedContent());
        $client = User::factory()->cliente()->create();
        $this->actingAs($client)->get(route('listas.rpz-preview.download', $this->lista))->assertForbidden();
    }

    public function test_search_finds_and_normalizes_domain(): void
    {
        Dominio::factory()->for($this->lista)->create(['dominio' => 'pizza.com.br']);
        $this->actingAs($this->admin)->get(route('listas.rpz-preview', [$this->lista, 'domain' => '  PIZZA.COM.BR  ']))
            ->assertOk()->assertSee('Incluído na RPZ:')->assertSee('SIM')->assertSee('pizza.com.br CNAME .');
    }

    public function test_search_reports_excluded_inactive_and_missing(): void
    {
        Dominio::factory()->for($this->lista)->inativo()->create(['dominio' => 'excluido.example', 'inactive_reason' => 'anatel_exclusion']);
        Dominio::factory()->for($this->lista)->inativo()->create(['dominio' => 'inativo.example']);
        $this->actingAs($this->admin)->get(route('listas.rpz-preview', [$this->lista, 'domain' => 'excluido.example']))->assertSee('exclusão administrativa');
        $this->actingAs($this->admin)->get(route('listas.rpz-preview', [$this->lista, 'domain' => 'inativo.example']))->assertSee('Status: inativo');
        $this->actingAs($this->admin)->get(route('listas.rpz-preview', [$this->lista, 'domain' => 'ausente.example']))->assertSee('Domínio não encontrado nesta lista.');
    }

    public function test_real_format_fixture_is_exact(): void
    {
        foreach (['example.net', 'example.com', 'hypeflixplus.biz'] as $domain) Dominio::factory()->for($this->lista)->create(['dominio' => $domain]);
        $builder = app(RpzZoneBuilder::class);
        $expected = <<<'ZONE'
$TTL 60
@ SOA localhost. hostmaster.localhost. (
    1788264000  ; serial
    3600           ; refresh
    600            ; retry
    86400          ; expire
    60 )           ; minimum
  NS localhost.

; dominio canario -- sempre presente, usado para testar a sincronizacao
blocktest.localhost CNAME .

example.com CNAME .
*.example.com CNAME .
example.net CNAME .
*.example.net CNAME .
hypeflixplus.biz CNAME .
*.hypeflixplus.biz CNAME .
ZONE;
        $this->assertSame($expected."\n", $builder->build($builder->listQuery($this->lista), '.', '1788264000'));
    }

    public function test_operational_generation_uses_same_builder_format(): void
    {
        $server = Servidor::factory()->create();
        $server->listas()->attach($this->lista);
        Dominio::factory()->for($this->lista)->create(['dominio' => 'shared.example']);
        $expected = app(RpzZoneBuilder::class)->build(app(RpzZoneBuilder::class)->serverQuery($server->id), '.', (string) now()->timestamp);
        $this->assertSame($expected, $this->get(route('rpz.show', $server->token))->getContent());
    }

    public function test_manual_and_external_lists_can_be_previewed_without_server(): void
    {
        foreach ([Lista::factory()->create(), Lista::factory()->externa()->create()] as $list) {
            Dominio::factory()->for($list)->create(['dominio' => 'tipo-'.$list->id.'.example']);
            $this->actingAs($this->admin)->get(route('listas.rpz-preview', $list))->assertOk()->assertSee('tipo-'.$list->id.'.example CNAME .');
        }
    }

    public function test_opening_preview_creates_no_sync_log_or_operation(): void
    {
        $this->actingAs($this->admin)->get(route('listas.rpz-preview', $this->lista))->assertOk();
        $this->assertDatabaseCount('server_sync_logs', 0);
    }
}
