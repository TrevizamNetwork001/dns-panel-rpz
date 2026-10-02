<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\Servidor;
use App\Services\RpzPrimaryResponder;
use App\Services\RpzZoneBuilder;
use App\Services\RpzZoneBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RpzPrimaryResponderTest extends TestCase
{
    use RefreshDatabase;

    private function published(): RpzZone
    {
        Queue::fake();
        config(['rpz.static_artifacts_enabled' => true]);
        Storage::fake('local');
        $server = Servidor::factory()->openAccess()->create();
        $list = Lista::factory()->create();
        $server->listas()->attach($list);
        Dominio::factory()->for($list)->create(['dominio' => 'blocked.example']);
        app(RpzZoneBuildService::class)->build('server:'.$server->id);

        return RpzZone::where('zone_key', 'server:'.$server->id)->firstOrFail();
    }

    private function dnsQuery(?string $name = null, int $flags = 0, int $type = 6, int $class = 1): string
    {
        $wire = '';
        foreach (explode('.', $name ?? app(RpzZoneBuilder::class)->panelHost()) as $label) {
            $wire .= chr(strlen($label)).$label;
        }

        return pack('nnnnnn', 123, $flags, 1, 0, 0, 0).$wire."\0".pack('nn', $type, $class);
    }

    public function test_probe_uses_published_serial_even_if_a_new_build_reserved_a_serial(): void
    {
        $zone = $this->published();
        $serial = $zone->currentVersion->serial;
        $zone->update(['last_serial' => $serial + 1]);
        $reply = app(RpzPrimaryResponder::class)->respond($this->dnsQuery(), '127.0.0.1');
        $this->assertNotNull($reply);
        // SOA ends with five uint32 fields: serial, refresh, retry, expire, minimum.
        $this->assertSame($serial, unpack('Nserial', substr($reply, -20, 4))['serial']);
    }

    public function test_only_soa_queries_for_our_zone_are_answered(): void
    {
        $this->published();
        $responder = app(RpzPrimaryResponder::class);
        foreach ([$this->dnsQuery('unrelated.example'), $this->dnsQuery(flags: 0x8000), $this->dnsQuery(flags: 0x2000), $this->dnsQuery(type: 1), $this->dnsQuery(class: 3)] as $packet) {
            $this->assertNull($responder->respond($packet, '127.0.0.1'), bin2hex($packet));
        }
        $this->assertNotNull($responder->respond($this->dnsQuery(strtoupper(app(RpzZoneBuilder::class)->panelHost())), '127.0.0.1'));
        $this->assertNull($responder->respond($this->dnsQuery(), '192.0.2.99'));
    }

    public function test_ambiguous_source_address_never_selects_an_arbitrary_server(): void
    {
        $this->published();
        Servidor::factory()->openAccess()->create();
        $this->assertNull(app(RpzPrimaryResponder::class)->respond($this->dnsQuery(), '127.0.0.1'));
    }

    public function test_missing_artifact_does_not_advertise_a_serial(): void
    {
        $zone = $this->published();
        Storage::disk('local')->delete($zone->currentVersion->file_path);
        $this->assertNull(app(RpzPrimaryResponder::class)->respond($this->dnsQuery(), '127.0.0.1'));
    }

    public function test_dynamic_http_mode_does_not_advertise_static_serial(): void
    {
        $this->published();
        config(['rpz.static_artifacts_enabled' => false]);
        $this->assertNull(app(RpzPrimaryResponder::class)->respond($this->dnsQuery(), '127.0.0.1'));
    }
}
