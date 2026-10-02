<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\ServerSyncLog;
use App\Models\Servidor;
use App\Models\User;
use App\Services\RpzZoneBuilder;
use App\Services\RpzZoneVersioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o contrato da fase RPZ-SERIAL-1/RPZ-OBS-1 (auditoria tecnica de
 * 29/09/2026): serial persistido e monotonico por zona logica, content_hash
 * como fonte de verdade pra decidir mudanca, escopos server/company
 * isolados, bootstrap a partir do CRC legado e observabilidade sem alegar
 * aplicacao no Unbound.
 */
class RpzZoneVersioningTest extends TestCase
{
    use RefreshDatabase;

    private function servidorComLista(array $dominios = ['exemplo.example']): Servidor
    {
        $servidor = Servidor::factory()->openAccess()->create();
        $lista = Lista::factory()->create(['empresa_id' => $servidor->empresa_id]);
        $servidor->listas()->attach($lista);
        foreach ($dominios as $dominio) {
            Dominio::factory()->for($lista)->create(['dominio' => $dominio]);
        }

        return $servidor->fresh();
    }

    public function test_repeated_requests_with_unchanged_content_keep_same_serial_and_revision(): void
    {
        $servidor = $this->servidorComLista();

        $this->get(route('rpz.show', $servidor->token))->assertOk();
        $this->get(route('rpz.show', $servidor->token))->assertOk();

        $zona = RpzZone::where('zone_key', "server:{$servidor->id}")->firstOrFail();
        $this->assertSame(1, $zona->revision);

        $r1 = $this->get(route('rpz.show', $servidor->token));
        $r2 = $this->get(route('rpz.show', $servidor->token));
        $this->assertSame($r1->headers->get('X-Rpz-Serial'), $r2->headers->get('X-Rpz-Serial'));
        $this->assertSame($r1->headers->get('X-Rpz-Revision'), $r2->headers->get('X-Rpz-Revision'));
        $this->assertSame($r1->headers->get('X-Rpz-Content-Hash'), $r2->headers->get('X-Rpz-Content-Hash'));
    }

    public function test_content_change_advances_serial_and_revision_by_one(): void
    {
        $servidor = $this->servidorComLista();
        $r1 = $this->get(route('rpz.show', $servidor->token));
        $serialAntes = (int) $r1->headers->get('X-Rpz-Serial');
        $revisionAntes = (int) $r1->headers->get('X-Rpz-Revision');

        Dominio::factory()->for($servidor->listas()->first())->create(['dominio' => 'novo-dominio.example']);

        $r2 = $this->get(route('rpz.show', $servidor->token));
        $this->assertSame($serialAntes + 1, (int) $r2->headers->get('X-Rpz-Serial'));
        $this->assertSame($revisionAntes + 1, (int) $r2->headers->get('X-Rpz-Revision'));
        $this->assertNotSame($r1->headers->get('X-Rpz-Content-Hash'), $r2->headers->get('X-Rpz-Content-Hash'));
    }

    public function test_server_and_company_endpoints_have_independent_zone_keys_and_serials(): void
    {
        $empresa = Empresa::factory()->create(['rpz_slug' => 'empresa-teste-versionamento']);
        $servidorA = Servidor::factory()->openAccess()->create(['empresa_id' => $empresa->id]);
        $listaA = Lista::factory()->create(['empresa_id' => $empresa->id]);
        $servidorA->listas()->attach($listaA);
        Dominio::factory()->for($listaA)->create(['dominio' => 'so-servidor-a.example']);

        $rServidor = $this->get(route('rpz.show', $servidorA->token))->assertOk();
        $rEmpresa = $this->get(route('rpz.show', $empresa->rpz_slug))->assertOk();

        $this->assertNotSame($rServidor->headers->get('X-Rpz-Zone-Key'), $rEmpresa->headers->get('X-Rpz-Zone-Key'));
        $this->assertSame("server:{$servidorA->id}", $rServidor->headers->get('X-Rpz-Zone-Key'));
        $this->assertSame("company:{$empresa->id}", $rEmpresa->headers->get('X-Rpz-Zone-Key'));
        $this->assertCount(2, RpzZone::all());
    }

    public function test_two_servers_of_same_company_evolve_independently(): void
    {
        $empresa = Empresa::factory()->create();
        $servidorA = Servidor::factory()->openAccess()->create(['empresa_id' => $empresa->id]);
        $listaA = Lista::factory()->create(['empresa_id' => $empresa->id]);
        $servidorA->listas()->attach($listaA);
        Dominio::factory()->for($listaA)->create(['dominio' => 'a.example']);

        $servidorB = Servidor::factory()->openAccess()->create(['empresa_id' => $empresa->id]);
        $listaB = Lista::factory()->create(['empresa_id' => $empresa->id]);
        $servidorB->listas()->attach($listaB);
        Dominio::factory()->for($listaB)->create(['dominio' => 'b.example']);

        $this->get(route('rpz.show', $servidorA->token))->assertOk();
        $this->get(route('rpz.show', $servidorB->token))->assertOk();

        // Muda so a lista de A -- a revision de B nao pode avancar.
        Dominio::factory()->for($listaA)->create(['dominio' => 'a2.example']);
        $this->get(route('rpz.show', $servidorA->token))->assertOk();
        $rB = $this->get(route('rpz.show', $servidorB->token))->assertOk();

        $zonaA = RpzZone::where('zone_key', "server:{$servidorA->id}")->firstOrFail();
        $zonaB = RpzZone::where('zone_key', "server:{$servidorB->id}")->firstOrFail();
        $this->assertSame(2, $zonaA->revision);
        $this->assertSame(1, $zonaB->revision);
        $this->assertSame((string) $zonaB->revision, $rB->headers->get('X-Rpz-Revision'));
    }

    public function test_bootstrap_uses_legacy_crc_only_on_first_generation(): void
    {
        $servidor = $this->servidorComLista();
        $builder = app(RpzZoneBuilder::class);
        $crcEsperado = (int) $builder->computeSerial($builder->serverQuery($servidor->id)) % (RpzZoneVersioner::SERIAL_MAX + 1);

        $r1 = $this->get(route('rpz.show', $servidor->token))->assertOk();
        $this->assertSame((string) $crcEsperado, $r1->headers->get('X-Rpz-Serial'));

        // Depois do bootstrap, uma mudanca avanca +1 a partir dali -- nunca mais
        // volta a ser um CRC recalculado (que poderia diminuir/colidir).
        Dominio::factory()->for($servidor->listas()->first())->create(['dominio' => 'novo.example']);
        $r2 = $this->get(route('rpz.show', $servidor->token))->assertOk();
        $this->assertSame($crcEsperado + 1, (int) $r2->headers->get('X-Rpz-Serial'));
    }

    public function test_response_body_is_byte_identical_regardless_of_versioning_source(): void
    {
        $servidor = $this->servidorComLista(['a.example', 'b.example']);
        $response = $this->get(route('rpz.show', $servidor->token))->assertOk();

        $zona = RpzZone::where('zone_key', "server:{$servidor->id}")->firstOrFail();
        $builder = app(RpzZoneBuilder::class);
        $esperado = $builder->build($builder->serverQuery($servidor->id), '.', (string) $zona->last_serial);

        $this->assertSame($esperado, $response->getContent());
    }

    public function test_sync_log_records_request_id_revision_and_hash_before_no_longer_claiming_applied(): void
    {
        $servidor = $this->servidorComLista();
        $this->get(route('rpz.show', $servidor->token))->assertOk();

        $log = ServerSyncLog::where('servidor_id', $servidor->id)->latest('id')->first();
        $this->assertNotNull($log->request_id);
        $this->assertSame("server:{$servidor->id}", $log->zone_key);
        $this->assertSame(1, $log->revision);
        $this->assertNotNull($log->content_hash);
        $this->assertGreaterThanOrEqual(1, $log->rule_count);
        $this->assertNotNull($log->duration_ms);
    }

    public function test_ui_no_longer_claims_the_server_synced(): void
    {
        $servidor = $this->servidorComLista();
        ServerSyncLog::create(['servidor_id' => $servidor->id, 'ip_address' => '203.0.113.5', 'dominios_count' => 5, 'created_at' => now()]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('servidores.show', $servidor));
        $response->assertOk()
            // Rotulo atual da view e "Zona consultada" (24eefd6); o dado cru
            // no controller continua "RPZ solicitada", so a exibicao mudou.
            ->assertSee('Zona consultada')
            ->assertDontSee('Servidor sincronizou')
            ->assertDontSee('domínios entregues');
    }
}
