<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\ServerSyncLog;
use App\Models\Servidor;
use App\Services\RpzZoneBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cobre o contrato do endpoint HTTP quando RPZ_STATIC_ARTIFACTS_ENABLED=true:
 * serve o artefato publicado sem tocar `dominios`, com fallback dinâmico
 * seguro pra zonas ainda não construídas -- e confirma que com a flag no
 * padrão (false) nada muda em relação ao comportamento já testado antes.
 */
class RpzStaticEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_flag_disabled_by_default_keeps_dynamic_behavior(): void
    {
        $this->assertFalse(config('rpz.static_artifacts_enabled'));
    }

    public function test_static_flag_enabled_serves_published_artifact_without_querying_dominios(): void
    {
        config(['rpz.static_artifacts_enabled' => true]);
        $servidor = Servidor::factory()->openAccess()->create();
        $lista = Lista::factory()->create(['empresa_id' => $servidor->empresa_id]);
        $servidor->listas()->attach($lista);
        Dominio::factory()->for($lista)->create(['dominio' => 'estatico.example']);

        app(RpzZoneBuildService::class)->build('server:'.$servidor->id);

        DB::enableQueryLog();
        $response = $this->get(route('rpz.show', $servidor->token));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertStringContainsString('estatico.example CNAME .', $response->getContent());
        $this->assertFalse(collect($queries)->contains(fn ($q) => str_contains(strtolower($q['query']), 'from "dominios"') || str_contains(strtolower($q['query']), 'from `dominios`')));
        $response->assertHeader('X-Rpz-File-Hash');
    }

    public function test_zone_without_published_version_falls_back_to_dynamic(): void
    {
        config(['rpz.static_artifacts_enabled' => true]);
        $servidor = Servidor::factory()->openAccess()->create();
        $lista = Lista::factory()->create(['empresa_id' => $servidor->empresa_id]);
        $servidor->listas()->attach($lista);
        Dominio::factory()->for($lista)->create(['dominio' => 'nunca-buildado.example']);

        // Nenhum build() foi chamado -- zona nunca tem current_version_id.
        $response = $this->get(route('rpz.show', $servidor->token));

        $response->assertOk();
        $this->assertStringContainsString('nunca-buildado.example CNAME .', $response->getContent());
        $this->assertNull(RpzZone::where('zone_key', 'server:'.$servidor->id)->value('current_version_id'));
    }

    public function test_company_static_path_logs_sync_for_every_matching_server(): void
    {
        config(['rpz.static_artifacts_enabled' => true]);
        $empresa = Empresa::factory()->create(['rpz_slug' => 'empresa-estatica-teste']);
        $servidorA = Servidor::factory()->openAccess()->create(['empresa_id' => $empresa->id]);
        $servidorB = Servidor::factory()->openAccess()->create(['empresa_id' => $empresa->id]);
        $lista = Lista::factory()->create(['empresa_id' => $empresa->id]);
        $servidorA->listas()->attach($lista);
        Dominio::factory()->for($lista)->create(['dominio' => 'empresa.example']);

        app(RpzZoneBuildService::class)->build('company:'.$empresa->id);

        $response = $this->get(route('rpz.show', $empresa->rpz_slug));
        $response->assertOk();
        $this->assertStringContainsString('empresa.example CNAME .', $response->getContent());

        $this->assertSame(2, ServerSyncLog::whereIn('servidor_id', [$servidorA->id, $servidorB->id])->count());
    }

    public function test_missing_file_on_disk_falls_back_to_dynamic_instead_of_erroring(): void
    {
        config(['rpz.static_artifacts_enabled' => true]);
        $servidor = Servidor::factory()->openAccess()->create();
        $lista = Lista::factory()->create(['empresa_id' => $servidor->empresa_id]);
        $servidor->listas()->attach($lista);
        Dominio::factory()->for($lista)->create(['dominio' => 'arquivo-sumido.example']);

        app(RpzZoneBuildService::class)->build('server:'.$servidor->id);
        $zona = RpzZone::where('zone_key', 'server:'.$servidor->id)->firstOrFail();
        Storage::disk('local')->delete($zona->currentVersion->file_path);

        $response = $this->get(route('rpz.show', $servidor->token));

        $response->assertOk();
        $this->assertStringContainsString('arquivo-sumido.example CNAME .', $response->getContent());
    }
}
