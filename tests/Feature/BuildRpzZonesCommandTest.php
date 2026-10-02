<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\Servidor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildRpzZonesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_zones_without_writing_anything(): void
    {
        Servidor::factory()->create(['status' => 'active']);

        $this->artisan('rpz:build --all --dry-run')->assertExitCode(0);

        $this->assertSame(0, RpzZone::count());
    }

    public function test_all_bootstraps_every_active_server_and_company(): void
    {
        $empresa = Empresa::factory()->create();
        $servidor = Servidor::factory()->create(['empresa_id' => $empresa->id]);
        $lista = Lista::factory()->create(['empresa_id' => $empresa->id]);
        $servidor->listas()->attach($lista);
        Dominio::factory()->for($lista)->create(['dominio' => 'cli-bootstrap.example']);

        $this->artisan('rpz:build --all')->assertExitCode(0);

        $zonaServer = RpzZone::where('zone_key', 'server:'.$servidor->id)->first();
        $zonaCompany = RpzZone::where('zone_key', 'company:'.$empresa->id)->first();
        $this->assertNotNull($zonaServer?->current_version_id);
        $this->assertNotNull($zonaCompany?->current_version_id);
    }

    public function test_single_zone_option_builds_only_that_zone(): void
    {
        $servidorA = Servidor::factory()->create();
        $listaA = Lista::factory()->create(['empresa_id' => $servidorA->empresa_id]);
        $servidorA->listas()->attach($listaA);
        Dominio::factory()->for($listaA)->create(['dominio' => 'a.example']);

        $servidorB = Servidor::factory()->create();
        $listaB = Lista::factory()->create(['empresa_id' => $servidorB->empresa_id]);
        $servidorB->listas()->attach($listaB);
        Dominio::factory()->for($listaB)->create(['dominio' => 'b.example']);

        $this->artisan('rpz:build --zone=server:'.$servidorA->id)->assertExitCode(0);

        $this->assertNotNull(RpzZone::where('zone_key', 'server:'.$servidorA->id)->first()?->current_version_id);
        $this->assertNull(RpzZone::where('zone_key', 'server:'.$servidorB->id)->first());
    }

    public function test_no_arguments_fails_instead_of_silently_doing_nothing(): void
    {
        $this->artisan('rpz:build')->assertExitCode(1);
    }

    public function test_company_without_active_license_is_skipped_not_a_failure(): void
    {
        // Achado no ensaio pre-cutover (29/09/2026): rpz:build --all saia com
        // exit code 1 so porque uma empresa ativa mas sem licenca valida
        // (mesmo gate do endpoint dinamico) retornava skipped_inactive -- isso
        // nao e falha de build, e comportamento esperado.
        $empresa = Empresa::factory()->create();
        $empresa->licencas()->delete(); // sem nenhuma licenca ativa

        $this->artisan('rpz:build --all')->assertExitCode(0);

        $this->assertNull(RpzZone::where('zone_key', 'company:'.$empresa->id)->first()?->current_version_id);
    }
}
