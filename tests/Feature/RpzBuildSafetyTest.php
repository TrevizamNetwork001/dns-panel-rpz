<?php

namespace Tests\Feature;

use App\Jobs\BuildRpzZone;
use App\Jobs\NotifyRpzZone;
use App\Models\Dominio;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\Servidor;
use App\Services\DomainNormalizer;
use App\Services\RpzDirtyMarker;
use App\Services\RpzZoneBuilder;
use App\Services\RpzZoneBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RpzBuildSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function scope(): array
    {
        Storage::fake('local');
        Queue::fake();
        $server = Servidor::factory()->create();
        $list = Lista::factory()->create();
        $server->listas()->attach($list);
        $domain = Dominio::factory()->for($list)->create(['dominio' => 'first.example']);

        return [$server, $domain, 'server:'.$server->id];
    }

    public function test_first_dirty_mark_survives_lost_queue_job_and_can_be_reconciled(): void
    {
        [$server, , $key] = $this->scope();
        app(RpzDirtyMarker::class)->markServer($server->id);
        $zone = RpzZone::where('zone_key', $key)->firstOrFail();
        $this->assertTrue($zone->needsRebuild());
        $this->assertNull($zone->current_version_id);
        $this->artisan('rpz:build --dirty')->assertSuccessful();
        $this->assertNotNull($zone->fresh()->current_version_id);
        $this->assertFalse($zone->fresh()->needsRebuild());
    }

    public function test_first_static_build_preserves_legacy_serial_instead_of_resetting_to_one(): void
    {
        [$server, , $key] = $this->scope();
        $builder = app(RpzZoneBuilder::class);
        $legacy = (int) $builder->computeSerial($builder->serverQuery($server->id));
        $result = app(RpzZoneBuildService::class)->build($key);
        $this->assertSame($legacy, $result['serial']);
    }

    public function test_mutation_after_stale_check_is_caught_at_publication(): void
    {
        [$server, $domain, $key] = $this->scope();
        $service = app(RpzZoneBuildService::class);
        $service->build($key);
        $zone = RpzZone::where('zone_key', $key)->firstOrFail();
        $previous = $zone->current_version_id;
        $domain->update(['dominio' => 'second.example']);
        app(RpzDirtyMarker::class)->markServer($server->id);
        $reads = 0;
        DB::listen(function ($event) use (&$reads, $zone): void {
            if (str_starts_with($event->sql, 'select "desired_generation"')) {
                $reads++;
                if ($reads === 2) {
                    DB::table('rpz_zones')->where('id', $zone->id)->increment('desired_generation');
                }
            }
        });
        Queue::fake();
        $result = $service->build($key);
        $this->assertSame('stale_discarded', $result['status']);
        $this->assertSame($previous, $zone->fresh()->current_version_id);
        $this->assertTrue($zone->fresh()->needsRebuild());
        Queue::assertNotPushed(NotifyRpzZone::class);
        Queue::assertPushed(BuildRpzZone::class);
    }

    public function test_generation_exception_preserves_published_file_and_cleans_staging(): void
    {
        [$server, $domain, $key] = $this->scope();
        app(RpzZoneBuildService::class)->build($key);
        $zone = RpzZone::where('zone_key', $key)->firstOrFail();
        $previous = $zone->current_version_id;
        $domain->update(['dominio' => 'second.example']);
        app(RpzDirtyMarker::class)->markServer($server->id);
        $mock = \Mockery::mock(RpzZoneBuilder::class, [app(DomainNormalizer::class)])->makePartial();
        $this->instance(RpzZoneBuilder::class, $mock);
        $mock->shouldReceive('renderZone')->once()->andReturnUsing(function () {
            yield '$TTL 60';
            throw new \RuntimeException('simulated write interruption');
        });
        // Use a real builder for the canonical payload, mock only rendering.
        $this->assertSame('failed', app(RpzZoneBuildService::class)->build($key)['status']);
        $this->assertSame($previous, $zone->fresh()->current_version_id);
        $this->assertSame([], Storage::disk('local')->files('rpz/'.$zone->id.'/staging'));
    }

    public function test_real_file_lock_prevents_competing_builder(): void
    {
        [, , $key] = $this->scope();
        Storage::disk('local')->makeDirectory('rpz/locks');
        $lock = fopen(Storage::disk('local')->path('rpz/locks/'.str_replace(':', '_', $key).'.lock'), 'c');
        flock($lock, LOCK_EX);
        try {
            $this->assertSame('skipped_locked', app(RpzZoneBuildService::class)->build($key)['status']);
            $this->assertSame(0, RpzZone::count());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function test_missing_server_is_skipped_without_creating_foreign_key_violation(): void
    {
        Storage::fake('local');
        $this->assertSame('skipped_inactive', app(RpzZoneBuildService::class)->build('server:999999')['status']);
        $this->assertSame(0, RpzZone::count());
    }
}
