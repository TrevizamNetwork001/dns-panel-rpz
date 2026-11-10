<?php

namespace Tests\Feature;

use App\Jobs\NotifyRpzZone;
use App\Models\Dominio;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\Servidor;
use App\Models\User;
use App\Services\RpzNotifyTransport;
use App\Services\RpzPrimaryNotifier;
use App\Services\RpzZoneBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RpzNotifyTest extends TestCase
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
        Dominio::factory()->for($list)->create(['dominio' => 'remove.example']);
        config(['rpz.notify_enabled' => true, 'rpz.primary_enabled' => false, 'rpz.notify_server_ids' => [(string) $server->id]]);
        app(RpzZoneBuildService::class)->build('server:'.$server->id);

        return RpzZone::where('zone_key', 'server:'.$server->id)->firstOrFail();
    }

    public function test_notify_accepts_host_cidr_and_persists_unconfirmed_delivery(): void
    {
        $zone = $this->published();
        $this->mock(RpzNotifyTransport::class)->shouldReceive('send')->once()->with('127.0.0.1', $zone->zone_name)
            ->andReturn(['status' => 'unconfirmed', 'attempts' => 3, 'error' => 'No matching NOTIFY response']);
        app(RpzPrimaryNotifier::class)->notify($zone);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rpz.notify.unconfirmed']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'rpz.notify.acknowledged']);
    }

    public function test_disabled_or_non_pilot_servers_never_send(): void
    {
        $zone = $this->published();
        $this->mock(RpzNotifyTransport::class)->shouldNotReceive('send');
        config(['rpz.notify_enabled' => false]);
        app(RpzPrimaryNotifier::class)->notify($zone);
        config(['rpz.notify_enabled' => true, 'rpz.notify_server_ids' => []]);
        app(RpzPrimaryNotifier::class)->notify($zone);
    }

    public function test_ipv6_failure_does_not_prevent_ipv4_and_ack_is_recorded(): void
    {
        $zone = $this->published();
        $server = Servidor::findOrFail($zone->servidor_id);
        $server->allowedIps()->delete();
        $server->allowedIps()->create(['ip_cidr' => '2001:db8::1/128', 'status' => 'active']);
        $server->allowedIps()->create(['ip_cidr' => '127.0.0.1/32', 'status' => 'active']);
        $server->allowedIps()->create(['ip_cidr' => '192.0.2.0/24', 'status' => 'active']);
        $this->mock(RpzNotifyTransport::class, function ($mock) use ($zone) {
            $mock->shouldReceive('send')->once()->with('2001:db8::1', $zone->zone_name)->andThrow(new \RuntimeException('Network unreachable'));
            $mock->shouldReceive('send')->once()->with('127.0.0.1', $zone->zone_name)->andReturn(['status' => 'acknowledged', 'attempts' => 2, 'error' => null]);
        });
        app(RpzPrimaryNotifier::class)->notify($zone);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rpz.notify.failed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rpz.notify.acknowledged']);
    }

    public function test_deactivation_publishes_without_domain_and_enqueues_new_version_notify(): void
    {
        $zone = $this->published();
        $oldVersion = $zone->current_version_id;
        $domain = Dominio::where('dominio', 'remove.example')->firstOrFail();
        $this->actingAs(User::factory()->admin()->create())->patch(route('dominios.toggle', $domain))->assertRedirect();
        app(RpzZoneBuildService::class)->build($zone->zone_key);
        $zone->refresh();
        $this->assertNotSame($oldVersion, $zone->current_version_id);
        $this->assertStringNotContainsString('remove.example', Storage::disk('local')->get($zone->currentVersion->file_path));
        Queue::assertPushed(NotifyRpzZone::class, fn ($job) => $job->zoneKey === $zone->zone_key && $job->versionId === $zone->current_version_id);
        $this->mock(RpzNotifyTransport::class)->shouldNotReceive('send');
        (new NotifyRpzZone($zone->zone_key, $oldVersion))->handle(app(RpzPrimaryNotifier::class));
    }

    public function test_duplicate_in_another_list_means_no_new_publication_or_notify(): void
    {
        $zone = $this->published();
        $server = Servidor::findOrFail($zone->servidor_id);
        $list = Lista::factory()->create();
        $server->listas()->attach($list);
        Dominio::factory()->for($list)->create(['dominio' => 'remove.example']);
        Queue::fake();
        $domain = Dominio::where('dominio', 'remove.example')->firstOrFail();
        $this->actingAs(User::factory()->admin()->create())->delete(route('dominios.destroy', $domain))->assertRedirect();
        $result = app(RpzZoneBuildService::class)->build($zone->zone_key);
        $this->assertSame('unchanged', $result['status']);
        Queue::assertNotPushed(NotifyRpzZone::class);
    }

    public function test_company_notify_only_targets_mapped_pilot_servers_of_that_company(): void
    {
        $serverZone = $this->published();
        config(['rpz.primary_company_server_ids' => [(string) $serverZone->servidor_id]]);
        app(RpzZoneBuildService::class)->build('company:'.Servidor::findOrFail($serverZone->servidor_id)->empresa_id);
        $company = RpzZone::where('zone_key', 'company:'.Servidor::findOrFail($serverZone->servidor_id)->empresa_id)->firstOrFail();
        $this->mock(RpzNotifyTransport::class)->shouldReceive('send')->once()
            ->with('127.0.0.1', $company->zone_name)
            ->andReturn(['status' => 'acknowledged', 'attempts' => 1, 'error' => null]);
        $notifier = app(RpzPrimaryNotifier::class);
        $notifier->notify($serverZone);
        $notifier->notify($company);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rpz.notify.acknowledged', 'target_id' => $serverZone->servidor_id]);
        config(['rpz.notify_server_ids' => []]);
        $notifier->notify($company);
        config(['rpz.notify_server_ids' => [(string) $serverZone->servidor_id], 'rpz.primary_company_server_ids' => []]);
        $notifier->notify($company);
    }
}
