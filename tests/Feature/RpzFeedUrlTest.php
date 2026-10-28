<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Servidor;
use App\Models\User;
use App\Services\RpzFeedUrl;
use App\Services\RpzZoneBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RpzFeedUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_origin_changes_without_changing_zone_or_mikrotik_urls(): void
    {
        $company = Empresa::factory()->create(['rpz_slug' => 'uniao']);
        $server = Servidor::factory()->create(['empresa_id' => $company->id]);
        $zone = app(RpzZoneBuilder::class)->panelHost();
        $mikrotik = $company->mikrotikEndpointUrl();
        config(['rpz.feed_base_url' => 'https://feeds.example.net/']);
        $this->assertSame('https://feeds.example.net/rpz/uniao.zone', $company->rpzEndpointUrl());
        $this->assertSame($company->rpzEndpointUrl(), $server->preferredRpzEndpointUrl());
        $this->assertSame('https://feeds.example.net/rpz/'.$server->token.'.zone', $server->legacyRpzEndpointUrl());
        $this->assertSame($zone, app(RpzZoneBuilder::class)->panelHost());
        $this->assertSame($mikrotik, $company->mikrotikEndpointUrl());
        $company->update(['rpz_slug' => null]);
        $server->unsetRelation('empresa');
        $this->assertSame($server->legacyRpzEndpointUrl(), $server->preferredRpzEndpointUrl());
    }

    public function test_existing_origin_is_preserved_when_unconfigured(): void
    {
        config(['rpz.feed_base_url' => null]);
        $this->assertSame(url('/rpz/existing.zone'), RpzFeedUrl::make('existing'));
    }

    public function test_circular_download_origin_is_rejected(): void
    {
        config(['rpz.feed_base_url' => 'https://'.app(RpzZoneBuilder::class)->panelHost()]);
        $this->expectException(\InvalidArgumentException::class);
        RpzFeedUrl::make('example');
    }

    public function test_non_https_origin_is_rejected(): void
    {
        config(['rpz.feed_base_url' => 'http://feeds.example.net']);
        $this->expectException(\InvalidArgumentException::class);
        RpzFeedUrl::make('example');
    }

    public function test_feed_host_receives_the_same_protected_endpoint(): void
    {
        config(['rpz.feed_base_url' => 'https://feeds.example.net']);
        $company = Empresa::factory()->create(['rpz_slug' => 'uniao']);
        \App\Models\Licenca::factory()->for($company)->create();
        $server = Servidor::factory()->for($company)->create(['status' => 'active']);
        \App\Models\ServerAllowedIp::create(['servidor_id' => $server->id, 'ip_cidr' => '127.0.0.1/32', 'status' => 'active']);
        $this->get('https://feeds.example.net/rpz/uniao.zone')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->get('https://feeds.example.net/rpz/uniao.zone')->assertForbidden();
    }

    public function test_server_and_company_snippets_use_new_origin_and_preserve_zone(): void
    {
        config(['rpz.feed_base_url' => 'https://feeds.example.net']);
        $company = Empresa::factory()->create(['rpz_slug' => 'uniao']);
        $server = Servidor::factory()->create(['empresa_id' => $company->id, 'tipo_dns' => 'unbound']);
        $this->actingAs(User::factory()->admin()->create());
        foreach ([route('servidores.show', $server), route('empresas.show', $company)] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('https://feeds.example.net/rpz/uniao.zone', false)
                ->assertSee(app(RpzZoneBuilder::class)->panelHost());
        }
    }
}
