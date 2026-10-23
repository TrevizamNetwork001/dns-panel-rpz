<?php

namespace Tests\Feature;

use App\Jobs\BuildRpzZone;
use App\Models\Dominio;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\RpzZoneVersion;
use App\Models\Servidor;
use App\Services\ExternalListaSyncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CodeAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_reactivation_alone_marks_server_and_company_for_rebuild(): void
    {
        Queue::fake();
        $list = Lista::factory()->externa('feed', 'https://feed.example/domains', 'plain')->create();
        $server = Servidor::factory()->create();
        $list->servidores()->attach($server);
        $domains = [];
        for ($i = 0; $i < 100; $i++) {
            $domains[] = 'domain'.$i.'.example';
            Dominio::factory()->for($list)->create(['dominio' => end($domains), 'ativo' => $i !== 0]);
        }
        Http::fake(['feed.example/*' => Http::response(implode("\n", $domains))]);

        $result = app(ExternalListaSyncer::class)->sync($list);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(0, $result['adicionados']);
        $this->assertSame(0, $result['removidos']);
        $this->assertDatabaseHas('dominios', ['lista_id' => $list->id, 'dominio' => 'domain0.example', 'ativo' => true]);
        Queue::assertPushed(BuildRpzZone::class, fn ($job) => $job->zoneKey === 'server:'.$server->id);
        Queue::assertPushed(BuildRpzZone::class, fn ($job) => $job->zoneKey === 'company:'.$server->empresa_id);
        $this->assertSame(1, RpzZone::where('zone_key', 'server:'.$server->id)->value('desired_generation'));

        Queue::fake();
        app(ExternalListaSyncer::class)->sync($list);
        Queue::assertNothingPushed();
    }

    private function scope(Servidor $server): RpzZone
    {
        return RpzZone::create(['zone_key' => 'server:'.$server->id, 'scope_type' => 'server',
            'empresa_id' => $server->empresa_id, 'servidor_id' => $server->id,
            'zone_name' => 'policy.example', 'last_serial' => 1, 'revision' => 1,
            'content_hash' => str_repeat('a', 64)]);
    }

    private function version(RpzZone $zone, string $status): RpzZoneVersion
    {
        return RpzZoneVersion::create(['rpz_zone_id' => $zone->id, 'revision' => 1, 'serial' => 1,
            'content_hash' => str_repeat('a', 64), 'status' => $status,
            'file_path' => 'audit/'.$zone->id.'.zone']);
    }

    public function test_static_endpoint_never_serves_version_owned_by_another_company(): void
    {
        Queue::fake();
        Storage::fake('local');
        config(['rpz.static_artifacts_enabled' => true]);
        $server = Servidor::factory()->openAccess()->create();
        $other = Servidor::factory()->openAccess()->create();
        $zone = $this->scope($server);
        $foreign = $this->version($this->scope($other), 'published');
        Storage::disk('local')->put($foreign->file_path, 'other-company-private.example CNAME .');
        $zone->update(['current_version_id' => $foreign->id]);

        $response = $this->get(route('rpz.show', $server->token))->assertOk();

        $response->assertHeaderMissing('X-Rpz-File-Hash');
        $this->assertStringNotContainsString('other-company-private.example', (string) $response->getContent());
    }

    public function test_static_endpoint_never_serves_a_failed_candidate(): void
    {
        Queue::fake();
        Storage::fake('local');
        config(['rpz.static_artifacts_enabled' => true]);
        $server = Servidor::factory()->openAccess()->create();
        $zone = $this->scope($server);
        $version = $this->version($zone, 'failed');
        Storage::disk('local')->put($version->file_path, 'unpublished.example CNAME .');
        $zone->update(['current_version_id' => $version->id]);

        $response = $this->get(route('rpz.show', $server->token))->assertOk();

        $response->assertHeaderMissing('X-Rpz-File-Hash');
        $this->assertStringNotContainsString('unpublished.example', (string) $response->getContent());
    }
}
