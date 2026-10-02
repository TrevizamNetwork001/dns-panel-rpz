<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\Servidor;
use App\Models\User;
use App\Services\RpzZoneBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RpzCompanyMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_detaching_a_list_updates_company_artifact_as_well_as_server(): void
    {
        Storage::fake('local');
        $server = Servidor::factory()->create();
        $list = Lista::factory()->create();
        $server->listas()->attach($list);
        Dominio::factory()->for($list)->create(['dominio' => 'removed.example']);
        $key = 'company:'.$server->empresa_id;
        app(RpzZoneBuildService::class)->build($key);
        $this->actingAs(User::factory()->admin()->create())->delete(route('servidores.listas.detach', [$server, $list]))->assertRedirect();
        $zone = RpzZone::where('zone_key', $key)->firstOrFail();
        $this->assertStringNotContainsString('removed.example', Storage::disk('local')->get($zone->currentVersion->file_path));
    }

    public function test_deleting_server_updates_company_after_removal(): void
    {
        Storage::fake('local');
        $server = Servidor::factory()->create();
        $list = Lista::factory()->create();
        $server->listas()->attach($list);
        Dominio::factory()->for($list)->create(['dominio' => 'removed.example']);
        $key = 'company:'.$server->empresa_id;
        app(RpzZoneBuildService::class)->build($key);
        $this->actingAs(User::factory()->admin()->create())->delete(route('servidores.destroy', $server))->assertRedirect();
        $zone = RpzZone::where('zone_key', $key)->firstOrFail();
        $this->assertStringNotContainsString('removed.example', Storage::disk('local')->get($zone->currentVersion->file_path));
    }

    public function test_deleting_list_rebuilds_only_after_domains_are_removed(): void
    {
        Storage::fake('local');
        $server = Servidor::factory()->create();
        $list = Lista::factory()->create();
        $server->listas()->attach($list);
        Dominio::factory()->for($list)->create(['dominio' => 'removed.example']);
        $key = 'company:'.$server->empresa_id;
        app(RpzZoneBuildService::class)->build($key);
        $this->actingAs(User::factory()->admin()->create())->delete(route('listas.destroy', $list))->assertRedirect();
        $zone = RpzZone::where('zone_key', $key)->firstOrFail();
        $this->assertStringNotContainsString('removed.example', Storage::disk('local')->get($zone->currentVersion->file_path));
    }
}
