<?php

namespace Tests\Feature;

use App\Models\Lista;
use App\Models\RpzZone;
use App\Services\AnatelLegacyFeedSyncer;
use App\Services\ExternalListaSyncer;
use App\Services\RpzDnsMessage;
use App\Services\RpzNotifyTransport;
use App\Services\RpzPrimaryNotifier;
use App\Services\RpzPrimaryResponder;
use App\Services\RpzZoneBuilder;
use App\Services\RpzZoneBuildService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContaboPreparationSafetyTest extends TestCase
{
    public function test_new_switches_preserve_existing_defaults(): void
    {
        $this->assertTrue(config('rpz.endpoints_enabled'));
        $this->assertTrue(config('rpz.publication_enabled'));
        $this->assertTrue(config('lists.external_sync_enabled'));
    }

    public function test_disabled_endpoints_never_query_company_or_token(): void
    {
        config(['rpz.endpoints_enabled' => false]);
        DB::shouldReceive('connection')->never();
        foreach (['/rpz/unknown.zone', '/mikrotik/unknown.hosts'] as $url) {
            $this->get($url)->assertStatus(503)->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_disabled_publication_does_not_write_files_or_versions(): void
    {
        config(['rpz.publication_enabled' => false]);
        DB::shouldReceive('connection')->never();
        Storage::shouldReceive('disk')->never();
        $this->assertSame(['status' => 'skipped_disabled', 'zone_key' => 'server:1'],
            app(RpzZoneBuildService::class)->build('server:1'));
    }

    public function test_disabled_external_sync_blocks_normal_and_legacy_feeds(): void
    {
        config(['lists.external_sync_enabled' => false]);
        Http::preventStrayRequests();
        Http::fake();
        DB::shouldReceive('connection')->never();
        $list = new Lista(['origem' => 'externa', 'fonte_url' => 'https://feed.example/feed']);
        $this->assertSame('pausada', app(ExternalListaSyncer::class)->sync($list)['status']);
        $list->origem = 'anatel';
        $this->assertSame('pausada', app(AnatelLegacyFeedSyncer::class)->sync($list)['status']);
        Http::assertNothingSent();
    }

    public function test_disabled_delivery_also_blocks_the_soa_responder(): void
    {
        config(['rpz.endpoints_enabled' => false, 'rpz.static_artifacts_enabled' => true]);
        DB::shouldReceive('connection')->never();
        $packet = pack('nnnnnn', 1, 0x0100, 1, 0, 0, 0)
            .RpzDnsMessage::encodeName(app(RpzZoneBuilder::class)->panelHost()).pack('nn', 6, 1);
        $this->assertNull(app(RpzPrimaryResponder::class)->respond($packet, '127.0.0.1'));
    }

    public function test_disabled_publication_never_sends_notify_even_if_old_flags_are_enabled(): void
    {
        config(['rpz.publication_enabled' => false, 'rpz.notify_enabled' => true,
            'rpz.static_artifacts_enabled' => true, 'rpz.notify_server_ids' => ['1']]);
        DB::shouldReceive('connection')->never();
        $this->mock(RpzNotifyTransport::class)->shouldNotReceive('send');
        app(RpzPrimaryNotifier::class)->notify(new RpzZone(['zone_key' => 'server:1']));
    }
}
