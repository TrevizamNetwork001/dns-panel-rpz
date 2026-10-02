<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\Servidor;
use App\Services\RpzDirtyMarker;
use App\Services\RpzZoneBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RpzStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_empty_state_without_error(): void
    {
        $this->artisan('rpz:status')->assertExitCode(0);
    }

    public function test_shows_published_zone_and_stays_read_only(): void
    {
        $servidor = Servidor::factory()->create();
        $lista = Lista::factory()->create(['empresa_id' => $servidor->empresa_id]);
        $servidor->listas()->attach($lista);
        Dominio::factory()->for($lista)->create(['dominio' => 'status.example']);
        app(RpzZoneBuildService::class)->build('server:'.$servidor->id);

        $antes = RpzZone::where('zone_key', 'server:'.$servidor->id)->first();

        $this->artisan('rpz:status')
            ->expectsOutputToContain('server:'.$servidor->id)
            ->assertExitCode(0);

        $depois = RpzZone::where('zone_key', 'server:'.$servidor->id)->first();
        $this->assertSame($antes->current_version_id, $depois->current_version_id);
        $this->assertSame($antes->desired_generation, $depois->desired_generation);
        $this->assertSame($antes->built_generation, $depois->built_generation);
    }

    public function test_dirty_only_filters_correctly(): void
    {
        $servidorLimpo = Servidor::factory()->create();
        $lista = Lista::factory()->create(['empresa_id' => $servidorLimpo->empresa_id]);
        $servidorLimpo->listas()->attach($lista);
        Dominio::factory()->for($lista)->create();
        app(RpzZoneBuildService::class)->build('server:'.$servidorLimpo->id);

        $servidorSujo = Servidor::factory()->create();
        $listaSuja = Lista::factory()->create(['empresa_id' => $servidorSujo->empresa_id]);
        $servidorSujo->listas()->attach($listaSuja);
        Dominio::factory()->for($listaSuja)->create();
        app(RpzZoneBuildService::class)->build('server:'.$servidorSujo->id);
        // Queue::fake evita que o dispatch do job reconcilie sozinho na hora
        // (QUEUE_CONNECTION=sync em teste rodaria o job de verdade) -- aqui
        // queremos deixar a zona genuinamente suja pra testar o filtro.
        Queue::fake();
        app(RpzDirtyMarker::class)->markServer($servidorSujo->id);

        $this->artisan('rpz:status --dirty-only')
            ->expectsOutputToContain('server:'.$servidorSujo->id)
            ->doesntExpectOutputToContain('server:'.$servidorLimpo->id)
            ->assertExitCode(0);
    }
}
