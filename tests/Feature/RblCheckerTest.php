<?php

namespace Tests\Feature;

use App\Models\RblCheck;
use App\Models\RblEvent;
use App\Models\RblList;
use App\Models\RblTarget;
use App\Models\User;
use App\Services\Rbl\DnsblResolver;
use App\Services\Rbl\RblChecker;
use Database\Seeders\RblListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RblCheckerTest extends TestCase
{
    use RefreshDatabase;

    private function target(array $attributes = []): RblTarget
    {
        return RblTarget::create(array_merge(['name' => 'Mail', 'type' => 'ip', 'value' => '1.2.3.4', 'enabled' => true], $attributes));
    }

    private function list(array $attributes = []): RblList
    {
        return RblList::create(array_merge(['name' => 'Test RBL', 'type' => 'ip', 'dns_zone' => 'rbl.example.org', 'enabled' => true], $attributes));
    }

    private function dns(array $results): void
    {
        $this->instance(DnsblResolver::class, new class($results) extends DnsblResolver
        {
            public function __construct(private array $results) {}

            public function resolve(string $query, float $timeout): array
            {
                if (! $this->results) {
                    throw new \LogicException('Unexpected DNS query');
                }

                return array_shift($this->results);
            }
        });
    }

    public function test_admin_can_create_ipv4_target_and_view_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/rbl/targets', ['name' => 'Mail', 'type' => 'ip', 'value' => '1.2.3.4', 'category' => 'mail', 'enabled' => 1])
            ->assertRedirect('/rbl/targets/1');
        $this->assertDatabaseHas('rbl_targets', ['value' => '1.2.3.4', 'last_status' => 'unchecked']);
        $this->get('/rbl')->assertOk()->assertSee('Verificar agora');
        $this->get('/rbl/targets/create')->assertOk();
        $this->get('/rbl/targets/1')->assertOk()->assertSee('Histórico de checks');
        $this->get('/rbl/targets/1/checks')->assertOk();
    }

    public function test_admin_can_create_and_toggle_list(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/rbl/lists', ['name' => 'Example', 'dns_zone' => 'rbl.example.org', 'type' => 'ip', 'timeout_seconds' => 3])
            ->assertRedirect('/rbl');
        $this->assertDatabaseHas('rbl_lists', ['dns_zone' => 'rbl.example.org', 'enabled' => false]);
        $this->patch('/rbl/lists/1/toggle')->assertRedirect();
        $this->assertTrue(RblList::first()->enabled);
        $this->post('/rbl/lists', ['name' => 'Duplicate', 'dns_zone' => 'RBL.EXAMPLE.ORG', 'type' => 'ip', 'timeout_seconds' => 3])
            ->assertSessionHasErrors('dns_zone');
        $this->assertDatabaseCount('rbl_lists', 1);
    }

    public function test_invalid_target_list_and_timeout_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (['ip' => '999.2.3.4', 'cidr' => '1.2.3.4/33', 'domain' => 'https://example.org'] as $type => $value) {
            $this->post('/rbl/targets', ['name' => 'Invalid', 'type' => $type, 'value' => $value, 'enabled' => 1])->assertSessionHasErrors('value');
        }
        $this->post('/rbl/lists', ['name' => 'Invalid', 'dns_zone' => 'http://localhost/', 'type' => 'ip', 'timeout_seconds' => 60])
            ->assertSessionHasErrors(['dns_zone', 'timeout_seconds']);
        $this->assertDatabaseCount('rbl_targets', 0);
        $this->assertDatabaseCount('rbl_lists', 0);
    }

    public function test_all_routes_require_admin(): void
    {
        $target = $this->target();
        $list = $this->list();
        $routes = [['get', '/rbl'], ['get', '/rbl/targets/create'], ['post', '/rbl/targets'],
            ['get', "/rbl/targets/$target->id"], ['get', "/rbl/targets/$target->id/checks"],
            ['post', "/rbl/targets/$target->id/check"], ['patch', "/rbl/targets/$target->id/toggle"],
            ['post', '/rbl/lists'], ['patch', "/rbl/lists/$list->id/toggle"]];
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect('/login');
        }
        $this->actingAs(User::factory()->cliente()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
    }

    public function test_manual_check_records_history_and_event_lifecycle(): void
    {
        $target = $this->target();
        $this->list();
        $this->dns([['status' => 'listed', 'response' => '127.0.0.2'], ['status' => 'listed', 'response' => '127.0.0.4'], ['status' => 'clean']]);
        $this->actingAs(User::factory()->admin()->create());
        $this->post("/rbl/targets/$target->id/check")->assertRedirect("/rbl/targets/$target->id");
        $this->assertDatabaseHas('rbl_checks', ['query' => '4.3.2.1.rbl.example.org', 'status' => 'listed']);
        $first = RblEvent::first()->first_seen_at;
        $this->assertSame('listed', $target->fresh()->last_status);
        $this->travel(2)->minutes();
        $this->post("/rbl/targets/$target->id/check")->assertRedirect();
        $this->assertDatabaseCount('rbl_events', 1);
        $event = RblEvent::first();
        $this->assertTrue($event->first_seen_at->equalTo($first));
        $this->assertTrue($event->last_seen_at->gt($first));
        $this->assertSame('127.0.0.4', $event->last_response);
        $this->travel(2)->minutes();
        $this->post("/rbl/targets/$target->id/check")->assertRedirect();
        $this->assertDatabaseCount('rbl_checks', 3);
        $this->assertSame('resolved', $event->fresh()->status);
        $this->assertNotNull($event->fresh()->resolved_at);
        $this->assertSame('clean', $target->fresh()->last_status);
        $this->get("/rbl/targets/$target->id")->assertOk()->assertSee('resolved')->assertSee('127.0.0.4');
    }

    public function test_errors_do_not_resolve_open_events_and_new_listing_reopens(): void
    {
        $target = $this->target();
        $this->list();
        $this->dns([['status' => 'listed', 'response' => '127.0.0.2'], ['status' => 'timeout'], ['status' => 'error'], ['status' => 'clean'], ['status' => 'listed', 'response' => '127.0.0.2']]);
        foreach (['listed', 'error', 'error', 'clean', 'listed'] as $index => $expected) {
            app(RblChecker::class)->check($target);
            $this->assertSame($expected, $target->fresh()->last_status);
            if ($index < 3) {
                $this->assertSame('open', RblEvent::first()->status);
            }
            $this->travel(2)->minutes();
        }
        $this->assertDatabaseCount('rbl_events', 2);
        $this->assertSame(1, RblEvent::where('status', 'open')->count());
    }

    public function test_unsupported_targets_and_incompatible_lists_are_skipped_without_dns(): void
    {
        $this->mock(DnsblResolver::class)->shouldNotReceive('resolve');
        $this->list();
        foreach (['cidr' => '1.2.0.0/21', 'domain' => 'example.org', 'hostname' => 'mail.example.org', 'ip' => '2001:db8::1'] as $type => $value) {
            $target = $this->target(compact('type', 'value'));
            app(RblChecker::class)->check($target);
            $this->assertSame('skipped', $target->checks()->first()->status);
            $this->assertSame($target->type === 'cidr' ? 'skipped' : 'unchecked', $target->fresh()->last_status);
        }
        RblList::query()->update(['type' => 'domain']);
        app(RblChecker::class)->check($this->target());
        $this->assertDatabaseCount('rbl_events', 0);
        $this->assertSame(5, RblCheck::where('status', 'skipped')->count());
    }

    public function test_disabled_targets_no_lists_cooldown_and_shared_lock_are_controlled(): void
    {
        $target = $this->target(['enabled' => false]);
        $this->actingAs(User::factory()->admin()->create());
        $this->post("/rbl/targets/$target->id/check")->assertSessionHasErrors('check');
        $target->update(['enabled' => true]);
        $this->post("/rbl/targets/$target->id/check")->assertSessionHasErrors('check');
        $this->list();
        $this->dns([['status' => 'clean']]);
        $this->post("/rbl/targets/$target->id/check")->assertRedirect();
        $this->post("/rbl/targets/$target->id/check")->assertSessionHasErrors('check');
        $this->travel(2)->minutes();
        $lock = Cache::lock('rbl:manual-check', 60);
        $this->assertTrue($lock->get());
        $this->post("/rbl/targets/$target->id/check")->assertSessionHasErrors('check');
        $lock->release();
        $this->assertDatabaseCount('rbl_checks', 1);
    }

    public function test_disabled_lists_are_not_consulted_and_errors_have_priority_over_clean(): void
    {
        $target = $this->target();
        $this->list(['enabled' => false]);
        $this->list(['dns_zone' => 'a.example.org']);
        $this->list(['dns_zone' => 'b.example.org']);
        $this->dns([['status' => 'clean'], ['status' => 'timeout']]);
        app(RblChecker::class)->check($target);
        $this->assertDatabaseCount('rbl_checks', 2);
        $this->assertDatabaseCount('rbl_events', 0);
        $this->assertSame('error', $target->fresh()->last_status);
    }

    public function test_query_limit_records_skipped_and_never_claims_clean(): void
    {
        config(['rbl.max_checks_per_target' => 10]);
        for ($i = 0; $i < 11; $i++) {
            $this->list(['dns_zone' => "rbl$i.example.org"]);
        }
        $this->dns(array_fill(0, 10, ['status' => 'clean']));
        $target = $this->target();
        app(RblChecker::class)->check($target);
        $this->assertDatabaseCount('rbl_checks', 11);
        $this->assertSame(1, RblCheck::where('status', 'skipped')->count());
        $this->assertSame('unchecked', $target->fresh()->last_status);
    }

    public function test_seed_is_idempotent_and_preserves_configuration(): void
    {
        $this->seed(RblListSeeder::class);
        RblList::where('dns_zone', 'zen.spamhaus.org')->update(['enabled' => false]);
        $this->seed(RblListSeeder::class);
        $this->assertDatabaseCount('rbl_lists', 4);
        $this->assertDatabaseHas('rbl_lists', ['dns_zone' => 'zen.spamhaus.org', 'enabled' => false]);
        $this->assertDatabaseHas('rbl_lists', ['dns_zone' => 'dnsbl.sorbs.net', 'enabled' => false]);
    }
}
