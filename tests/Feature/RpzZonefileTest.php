<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Licenca;
use App\Models\Lista;
use App\Models\ServerAllowedIp;
use App\Models\ServerSyncLog;
use App\Models\Servidor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RpzZonefileTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_token_returns_404(): void
    {
        $this->get('/rpz/token-inexistente.zone')->assertStatus(404);
    }

    public function test_inactive_server_returns_404(): void
    {
        $servidor = Servidor::factory()->inactive()->create();

        $this->get("/rpz/{$servidor->token}.zone")->assertStatus(404);
    }

    public function test_server_of_inactive_company_returns_404(): void
    {
        $empresa = Empresa::factory()->inactive()->create();
        $servidor = Servidor::factory()->for($empresa)->create();

        $this->get("/rpz/{$servidor->token}.zone")->assertStatus(404);
    }

    public function test_server_of_company_without_active_license_returns_404(): void
    {
        $empresa = Empresa::factory()->semLicencaAtiva()->create();
        $servidor = Servidor::factory()->for($empresa)->create();

        $this->get("/rpz/{$servidor->token}.zone")->assertStatus(404);
    }

    public function test_server_of_company_with_expired_license_returns_404(): void
    {
        $empresa = Empresa::factory()->semLicencaAtiva()->create();
        Licenca::factory()->for($empresa)->expired()->create();
        $servidor = Servidor::factory()->for($empresa)->create();

        $this->get("/rpz/{$servidor->token}.zone")->assertStatus(404);
    }

    public function test_zonefile_always_includes_canary_domain_as_nxdomain(): void
    {
        $servidor = Servidor::factory()->openAccess()->create();

        $response = $this->get("/rpz/{$servidor->token}.zone");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/dns; charset=utf-8');
        $this->assertMatchesRegularExpression('/blocktest\.[^\s]+ CNAME \.$/m', $response->getContent());
    }

    public function test_zonefile_includes_active_domains_from_linked_active_listas(): void
    {
        $servidor = Servidor::factory()->openAccess()->create();
        $lista = Lista::factory()->create(['status' => 'active']);
        Dominio::factory()->for($lista)->create(['dominio' => 'malicioso.example']);
        $servidor->listas()->attach($lista);

        $content = $this->get("/rpz/{$servidor->token}.zone")->getContent();

        $this->assertStringContainsString('malicioso.example CNAME .', $content);
        $this->assertStringContainsString('*.malicioso.example CNAME .', $content);
    }

    public function test_zonefile_excludes_inactive_domains(): void
    {
        $servidor = Servidor::factory()->openAccess()->create();
        $lista = Lista::factory()->create(['status' => 'active']);
        Dominio::factory()->for($lista)->inativo()->create(['dominio' => 'desativado.example']);
        $servidor->listas()->attach($lista);

        $content = $this->get("/rpz/{$servidor->token}.zone")->getContent();

        $this->assertStringNotContainsString('desativado.example', $content);
    }

    public function test_zonefile_excludes_domains_from_inactive_listas(): void
    {
        $servidor = Servidor::factory()->openAccess()->create();
        $lista = Lista::factory()->inactive()->create();
        Dominio::factory()->for($lista)->create(['dominio' => 'lista-inativa.example']);
        $servidor->listas()->attach($lista);

        $content = $this->get("/rpz/{$servidor->token}.zone")->getContent();

        $this->assertStringNotContainsString('lista-inativa.example', $content);
    }

    public function test_zonefile_skips_owner_that_overflows_when_rpz_zone_name_is_appended(): void
    {
        $servidor = Servidor::factory()->openAccess()->create();
        $lista = Lista::factory()->create();
        $servidor->listas()->attach($lista);
        $tooLongForRelativeOwner = implode('.', [
            str_repeat('a', 63),
            str_repeat('b', 63),
            str_repeat('c', 63),
            str_repeat('d', 58),
        ]);
        $this->assertSame(250, strlen($tooLongForRelativeOwner));
        Dominio::factory()->for($lista)->create(['dominio' => $tooLongForRelativeOwner]);

        $content = $this->get("/rpz/{$servidor->token}.zone")->getContent();

        $this->assertStringNotContainsString($tooLongForRelativeOwner, $content);
        $this->assertStringContainsString('blocktest.', $content);
    }

    public function test_nxdomain_mode_uses_cname_root(): void
    {
        $servidor = Servidor::factory()->openAccess()->create(['bloqueio_modo' => 'nxdomain']);
        $lista = Lista::factory()->create();
        Dominio::factory()->for($lista)->create(['dominio' => 'bloqueado.example']);
        $servidor->listas()->attach($lista);

        $content = $this->get("/rpz/{$servidor->token}.zone")->getContent();

        $this->assertStringContainsString('bloqueado.example CNAME .', $content);
    }

    public function test_redirect_mode_uses_cname_to_panel_host(): void
    {
        $servidor = Servidor::factory()->openAccess()->redirect()->create();
        $lista = Lista::factory()->create();
        Dominio::factory()->for($lista)->create(['dominio' => 'bloqueado.example']);
        $servidor->listas()->attach($lista);

        $content = $this->get("/rpz/{$servidor->token}.zone")->getContent();

        $panelHost = parse_url(config('app.url'), PHP_URL_HOST);

        $this->assertStringContainsString("bloqueado.example CNAME {$panelHost}.", $content);
        $this->assertStringNotContainsString('bloqueado.example CNAME .', $content);
    }

    public function test_ip_restriction_blocks_disallowed_ip(): void
    {
        $servidor = Servidor::factory()->create(['ip_restriction_enabled' => true]);
        ServerAllowedIp::create([
            'servidor_id' => $servidor->id,
            'ip_cidr' => '203.0.113.0/24',
            'status' => 'active',
        ]);

        $this->get("/rpz/{$servidor->token}.zone")->assertStatus(404);
    }

    public function test_ip_restriction_allows_matching_cidr(): void
    {
        $servidor = Servidor::factory()->create(['ip_restriction_enabled' => true]);
        ServerAllowedIp::create([
            'servidor_id' => $servidor->id,
            'ip_cidr' => '127.0.0.0/8',
            'status' => 'active',
        ]);

        $this->get("/rpz/{$servidor->token}.zone")->assertStatus(200);
    }

    public function test_server_without_any_registered_ip_returns_404_even_with_restriction_disabled(): void
    {
        $servidor = Servidor::factory()->create(['ip_restriction_enabled' => false]);

        $this->get("/rpz/{$servidor->token}.zone")->assertStatus(404);
    }

    public function test_fetching_zonefile_creates_sync_log_and_updates_last_synced_at(): void
    {
        $servidor = Servidor::factory()->openAccess()->create();

        $this->assertNull($servidor->last_synced_at);

        $this->get("/rpz/{$servidor->token}.zone")->assertStatus(200);

        $this->assertDatabaseCount('server_sync_logs', 1);
        $this->assertEquals(1, ServerSyncLog::where('servidor_id', $servidor->id)->count());
        $this->assertNotNull($servidor->fresh()->last_synced_at);
    }

    public function test_zonefile_is_valid_dns_zone_syntax(): void
    {
        $servidor = Servidor::factory()->openAccess()->create();
        $lista = Lista::factory()->create();
        Dominio::factory()->for($lista)->create(['dominio' => 'exemplo-malicioso.test']);
        $servidor->listas()->attach($lista);

        $content = $this->get("/rpz/{$servidor->token}.zone")->getContent();

        $tmpFile = tempnam(sys_get_temp_dir(), 'rpz_test_zone_');
        file_put_contents($tmpFile, $content);

        $panelHost = parse_url(config('app.url'), PHP_URL_HOST);
        exec("named-checkzone {$panelHost} {$tmpFile} 2>&1", $output, $exitCode);
        unlink($tmpFile);

        $this->assertSame(0, $exitCode, 'named-checkzone falhou: '.implode("\n", $output));
    }

    public function test_handles_large_domain_lists_without_high_memory_usage(): void
    {
        // Regressao: a versao antiga carregava tudo via Eloquent (with()/pluck()/
        // flatten() em memoria) e estourava um memory_limit de 128M com feeds de
        // threat intel grandes (dezenas de milhares de dominios). Simula esse
        // limite aqui pra garantir que a consulta enxuta (DB::table) nao volte
        // a esse padrao sem que o teste acuse.
        $servidor = Servidor::factory()->openAccess()->create();
        $lista = Lista::factory()->create();
        $servidor->listas()->attach($lista);

        $agora = now();
        $rows = [];
        for ($i = 0; $i < 20000; $i++) {
            $rows[] = [
                'lista_id' => $lista->id,
                'dominio' => "dominio-teste-{$i}.example",
                'ativo' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('dominios')->insert($chunk);
        }

        $limiteAnterior = ini_set('memory_limit', '128M');
        try {
            $response = $this->get("/rpz/{$servidor->token}.zone");
        } finally {
            ini_set('memory_limit', $limiteAnterior);
        }

        $response->assertStatus(200);
        // cada dominio gera 2 linhas (exato + wildcard) + 1 linha fixa do canario
        $this->assertSame(20000 * 2 + 1, substr_count($response->getContent(), ' CNAME .'));
    }
}
