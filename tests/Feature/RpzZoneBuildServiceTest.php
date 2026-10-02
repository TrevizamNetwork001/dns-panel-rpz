<?php

namespace Tests\Feature;

use App\Jobs\BuildRpzZone;
use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\RpzZoneVersion;
use App\Models\Servidor;
use App\Services\RpzDirtyMarker;
use App\Services\RpzZoneBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RpzZoneBuildServiceTest extends TestCase
{
    use RefreshDatabase;

    private function servidorComDominio(string $dominio = 'a.example'): Servidor
    {
        $servidor = Servidor::factory()->create();
        $lista = Lista::factory()->create(['empresa_id' => $servidor->empresa_id]);
        $servidor->listas()->attach($lista);
        Dominio::factory()->for($lista)->create(['dominio' => $dominio]);

        return $servidor->fresh();
    }

    public function test_first_build_bootstraps_and_publishes_even_with_generation_zero_zero(): void
    {
        $servidor = $this->servidorComDominio();
        $zoneKey = 'server:'.$servidor->id;

        $resultado = app(RpzZoneBuildService::class)->build($zoneKey);

        $this->assertSame('published', $resultado['status']);
        $zona = RpzZone::where('zone_key', $zoneKey)->firstOrFail();
        $this->assertNotNull($zona->current_version_id);
        $this->assertSame(1, $zona->revision);
    }

    public function test_unchanged_content_does_not_create_new_version_row(): void
    {
        $servidor = $this->servidorComDominio();
        $zoneKey = 'server:'.$servidor->id;
        $service = app(RpzZoneBuildService::class);

        $service->build($zoneKey);
        $this->assertSame(1, RpzZoneVersion::count());

        // Sem nenhuma mutacao real, desired_generation continua igual a
        // built_generation -- vira no-op antes mesmo de tocar dominios.
        $resultado = $service->build($zoneKey);
        $this->assertSame('noop', $resultado['status']);
        $this->assertSame(1, RpzZoneVersion::count());
    }

    public function test_content_change_creates_revision_plus_one_and_supersedes_previous(): void
    {
        $servidor = $this->servidorComDominio();
        $zoneKey = 'server:'.$servidor->id;
        $service = app(RpzZoneBuildService::class);
        $dirty = app(RpzDirtyMarker::class);

        $service->build($zoneKey);
        $v1 = RpzZone::where('zone_key', $zoneKey)->first()->current_version_id;

        // QUEUE_CONNECTION=sync no ambiente de teste roda BuildRpzZone na
        // hora do dispatch -- Queue::fake() aqui garante que so o build()
        // explicito abaixo (nao o markServer()) faz o trabalho, deixando o
        // teste explicito sobre qual chamada publica a nova revision.
        Queue::fake();
        Dominio::factory()->for($servidor->listas()->first())->create(['dominio' => 'b.example']);
        $dirty->markServer($servidor->id);
        $resultado = $service->build($zoneKey);

        $this->assertSame('published', $resultado['status']);
        $this->assertSame(2, $resultado['revision']);
        $zona = RpzZone::where('zone_key', $zoneKey)->first();
        $this->assertNotSame($v1, $zona->current_version_id);
        $this->assertSame('superseded', RpzZoneVersion::find($v1)->status);
        $this->assertSame('published', RpzZoneVersion::find($zona->current_version_id)->status);
    }

    public function test_file_hash_matches_actual_bytes_written(): void
    {
        $servidor = $this->servidorComDominio();
        $zoneKey = 'server:'.$servidor->id;
        app(RpzZoneBuildService::class)->build($zoneKey);

        $version = RpzZone::where('zone_key', $zoneKey)->first()->currentVersion;
        $bytes = file_get_contents(Storage::disk('local')->path($version->file_path));

        $this->assertSame(hash('sha256', $bytes), $version->file_hash);
        $this->assertSame(strlen($bytes), $version->file_size);
    }

    public function test_content_hash_is_stable_across_builds_with_no_change(): void
    {
        $servidor = $this->servidorComDominio();
        $zoneKey = 'server:'.$servidor->id;
        app(RpzZoneBuildService::class)->build($zoneKey);
        $hash1 = RpzZone::where('zone_key', $zoneKey)->first()->currentVersion->content_hash;

        app(RpzDirtyMarker::class)->markServer($servidor->id);
        app(RpzZoneBuildService::class)->build($zoneKey); // conteudo igual -> content_hash igual -> nao publica de novo

        $zona = RpzZone::where('zone_key', $zoneKey)->first();
        $this->assertSame($hash1, $zona->currentVersion->content_hash);
        $this->assertSame(1, RpzZoneVersion::where('status', 'published')->count());
    }

    public function test_server_and_company_zones_are_isolated(): void
    {
        $empresa = Empresa::factory()->create();
        $servidorA = Servidor::factory()->create(['empresa_id' => $empresa->id]);
        $listaA = Lista::factory()->create(['empresa_id' => $empresa->id]);
        $servidorA->listas()->attach($listaA);
        Dominio::factory()->for($listaA)->create(['dominio' => 'a.example']);

        $service = app(RpzZoneBuildService::class);
        $service->build('server:'.$servidorA->id);
        $service->build('company:'.$empresa->id);

        $zonaServer = RpzZone::where('zone_key', 'server:'.$servidorA->id)->firstOrFail();
        $zonaCompany = RpzZone::where('zone_key', 'company:'.$empresa->id)->firstOrFail();
        $this->assertNotSame($zonaServer->current_version_id, $zonaCompany->current_version_id);
    }

    public function test_wpad_canary_and_wildcard_are_preserved_in_published_file(): void
    {
        $servidor = $this->servidorComDominio();
        $lista = $servidor->listas()->first();
        Dominio::factory()->for($lista)->create(['dominio' => 'wpad.domain.name']);
        $zoneKey = 'server:'.$servidor->id;

        app(RpzZoneBuildService::class)->build($zoneKey);
        $version = RpzZone::where('zone_key', $zoneKey)->first()->currentVersion;
        $body = file_get_contents(Storage::disk('local')->path($version->file_path));

        $this->assertStringContainsString('blocktest.', $body);
        $this->assertStringContainsString('a.example CNAME .', $body);
        $this->assertStringContainsString('*.a.example CNAME .', $body);
        $this->assertStringContainsString('wpad.domain.name CNAME .', $body);
        $this->assertStringNotContainsString('*.wpad.domain.name', $body);
    }

    public function test_redirect_mode_target_reaches_published_file(): void
    {
        $servidor = Servidor::factory()->create(['bloqueio_modo' => 'redirect']);
        $lista = Lista::factory()->create(['empresa_id' => $servidor->empresa_id]);
        $servidor->listas()->attach($lista);
        Dominio::factory()->for($lista)->create(['dominio' => 'a.example']);
        $zoneKey = 'server:'.$servidor->id;

        app(RpzZoneBuildService::class)->build($zoneKey);
        $version = RpzZone::where('zone_key', $zoneKey)->first()->currentVersion;
        $body = file_get_contents(Storage::disk('local')->path($version->file_path));

        $this->assertStringContainsString('a.example CNAME '.parse_url(config('app.url'), PHP_URL_HOST).'.', $body);
    }

    public function test_unchanged_build_preserves_current_version(): void
    {
        $servidor = $this->servidorComDominio();
        $zoneKey = 'server:'.$servidor->id;
        $service = app(RpzZoneBuildService::class);
        $service->build($zoneKey);
        $publicado = RpzZone::where('zone_key', $zoneKey)->first()->current_version_id;

        app(RpzDirtyMarker::class)->markServer($servidor->id);
        $service->build($zoneKey);
        $zona = RpzZone::where('zone_key', $zoneKey)->first();
        $this->assertSame($publicado, $zona->current_version_id, 'conteudo nao mudou -- current_version_id nao deveria ter avancado');
    }

    public function test_dispatching_dirty_mark_enqueues_build_job(): void
    {
        Queue::fake();
        $servidor = $this->servidorComDominio();

        app(RpzDirtyMarker::class)->markServer($servidor->id);

        Queue::assertPushed(BuildRpzZone::class, fn (BuildRpzZone $job) => $job->zoneKey === 'server:'.$servidor->id);
    }

    public function test_repeated_build_does_not_duplicate_versions(): void
    {
        $servidor = $this->servidorComDominio();
        $zoneKey = 'server:'.$servidor->id;
        $service = app(RpzZoneBuildService::class);

        $service->build($zoneKey);
        $service->build($zoneKey);

        $this->assertSame(1, RpzZoneVersion::count());
    }

    public function test_current_version_id_belongs_to_the_same_rpz_zone(): void
    {
        // Garante a invariante que a FK sozinha nao cobre: current_version_id
        // aponta pra uma rpz_zone_versions.rpz_zone_id == rpz_zones.id, nunca
        // pra versao de outra zona.
        $servidorA = $this->servidorComDominio('a.example');
        $servidorB = $this->servidorComDominio('b.example');
        $service = app(RpzZoneBuildService::class);

        $service->build('server:'.$servidorA->id);
        $service->build('server:'.$servidorB->id);

        $zonaA = RpzZone::where('zone_key', 'server:'.$servidorA->id)->firstOrFail();
        $zonaB = RpzZone::where('zone_key', 'server:'.$servidorB->id)->firstOrFail();

        $this->assertSame($zonaA->id, $zonaA->currentVersion->rpz_zone_id);
        $this->assertSame($zonaB->id, $zonaB->currentVersion->rpz_zone_id);
        $this->assertNotSame($zonaA->currentVersion->rpz_zone_id, $zonaB->currentVersion->rpz_zone_id);
    }

    public function test_inactive_server_is_skipped_without_error(): void
    {
        $servidor = $this->servidorComDominio();
        $servidor->update(['status' => 'inactive']);
        $zoneKey = 'server:'.$servidor->id;

        $resultado = app(RpzZoneBuildService::class)->build($zoneKey);

        $this->assertSame('skipped_inactive', $resultado['status']);
    }
}
