<?php

namespace Tests\Feature;

use App\Models\RblCheck;
use App\Models\RblEvent;
use App\Models\RblList;
use App\Models\RblRun;
use App\Models\RblTarget;
use App\Models\RblTargetGroup;
use App\Models\User;
use App\Services\Rbl\DnsblResolver;
use App\Services\Rbl\RblChecker;
use App\Services\Rbl\TargetExpansion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RblGroupsCidrTest extends TestCase
{
    use RefreshDatabase;

    private function target(array $data = []): RblTarget
    {
        return RblTarget::create(array_merge(['name' => 'CGNAT teste', 'type' => 'cidr', 'value' => '203.0.113.0/30', 'enabled' => true], $data));
    }

    private function lists(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            RblList::create(['name' => 'RBL '.$i, 'dns_zone' => "rbl{$i}.example.org", 'type' => 'ip', 'enabled' => true, 'timeout_seconds' => 1]);
        }
    }

    private function dns(array $results): void
    {
        $mock = $this->mock(DnsblResolver::class);
        $mock->shouldReceive('queryFor')->andReturnUsing(fn ($ip, $zone) => implode('.', array_reverse(explode('.', $ip))).'.'.$zone);
        $mock->shouldReceive('resolve')->times(count($results))->andReturn(...$results);
    }

    public function test_admin_manages_groups_and_target_association(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $data = ['name' => 'CGNAT Bloco 1', 'category' => 'cgnat', 'enabled' => 1];
        $this->post('/rbl/groups', $data)->assertRedirect('/rbl/groups/1');
        $group = RblTargetGroup::firstOrFail();
        $this->assertSame('cgnat-bloco-1', $group->slug);
        foreach (['/rbl/groups', '/rbl/groups/create', '/rbl/groups/1', '/rbl/groups/1/edit'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/rbl/groups')->assertSee('CGNAT Bloco 1');
        $this->post('/rbl/targets', ['name' => 'Bloco associado', 'type' => 'cidr', 'value' => '203.0.113.0/30', 'enabled' => 1, 'rbl_target_group_id' => $group->id])->assertRedirect();
        $target = RblTarget::firstOrFail();
        $this->assertTrue($target->group->is($group));
        $this->get('/rbl/targets/'.$target->id.'/edit')->assertOk()->assertSee('CGNAT Bloco 1');
        $this->patch('/rbl/targets/'.$target->id, ['name' => 'Renomeado', 'enabled' => 1, 'rbl_target_group_id' => null])->assertRedirect();
        $this->assertNull($target->fresh()->rbl_target_group_id);
        $this->patch('/rbl/groups/1', array_merge($data, ['slug' => 'bloco-editado']))->assertRedirect();
        $this->patch('/rbl/groups/1/toggle')->assertRedirect();
        $this->assertFalse($group->fresh()->enabled);
    }

    public function test_group_routes_require_auth_and_admin(): void
    {
        $g = RblTargetGroup::create(['name' => 'Grupo', 'slug' => 'grupo']);
        $routes = [['get', '/rbl/groups'], ['get', '/rbl/groups/create'], ['post', '/rbl/groups'], ['get', "/rbl/groups/{$g->id}"], ['get', "/rbl/groups/{$g->id}/edit"], ['patch', "/rbl/groups/{$g->id}"], ['patch', "/rbl/groups/{$g->id}/toggle"]];
        foreach ($routes as [$method,$url]) {
            $this->$method($url)->assertRedirect('/login');
        }
        $this->actingAs(User::factory()->cliente()->create());
        foreach ($routes as [$method,$url]) {
            $this->$method($url)->assertForbidden();
        }
    }

    public function test_invalid_group_and_duplicate_slug_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        RblTargetGroup::create(['name' => 'Grupo', 'slug' => 'grupo']);
        $this->post('/rbl/groups', ['name' => 'Grupo', 'enabled' => 1])->assertSessionHasErrors('slug');
        $this->post('/rbl/targets', ['name' => 'X', 'type' => 'ip', 'value' => '203.0.113.1', 'enabled' => 1, 'rbl_target_group_id' => 999])->assertSessionHasErrors('rbl_target_group_id');
    }

    public function test_expansion_includes_network_broadcast_and_normalizes_host_bits(): void
    {
        $expander = app(TargetExpansion::class);
        $this->assertSame(['203.0.113.0', '203.0.113.1', '203.0.113.2', '203.0.113.3'], $expander->plan($this->target(['value' => '203.0.113.2/30']))['ips']);
        $this->assertCount(8, $expander->plan($this->target(['value' => '203.0.113.0/29']))['ips']);
        $this->assertSame(['255.255.255.255'], $expander->plan($this->target(['value' => '255.255.255.255/32']))['ips']);
        config(['rbl.max_cidr_ips' => 4]);
        $this->assertCount(8, $expander->plan($this->target(['value' => '203.0.113.0/29']))['ips']);
        config(['rbl.max_cidr_total_ips' => 128]);
        $this->assertSame([], $expander->plan($this->target(['value' => '203.0.113.0/24']))['ips']);
    }

    public function test_large_and_ipv6_cidrs_are_skipped_without_dns(): void
    {
        $this->lists();
        $this->mock(DnsblResolver::class)->shouldNotReceive('resolve');
        foreach (['203.0.112.0/21', '2001:db8::/126'] as $value) {
            $target = $this->target(['value' => $value]);
            app(RblChecker::class)->check($target);
            $this->assertSame('skipped', $target->fresh()->last_status);
            $this->assertSame('skipped', $target->checks()->first()->status);
        }
        $this->assertStringContainsString('limite', RblCheck::first()->error_message);
    }

    public function test_cidr_dry_run_plans_without_dns_or_writes_and_limit_counts_targets(): void
    {
        $this->lists(2);
        $target = $this->target();
        $this->target();
        $this->mock(DnsblResolver::class)->shouldNotReceive('resolve');
        $this->artisan('rbl:check', ['--target' => $target->id, '--dry-run' => true])->expectsOutputToContain('IPs planejados: 4 | Listas ativas: 2 | Checks planejados: 8')->assertSuccessful();
        $this->artisan('rbl:check', ['--limit' => 1, '--dry-run' => true])->expectsOutputToContain('Simulação: 1 alvo(s)')->assertSuccessful();
        $this->assertDatabaseCount('rbl_checks', 0);
        $this->assertDatabaseCount('rbl_runs', 0);
        $this->assertDatabaseCount('rbl_events', 0);
        $this->assertNull($target->fresh()->last_checked_at);
    }

    public function test_each_ip_has_check_query_and_independent_event_lifecycle(): void
    {
        $this->lists();
        $target = $this->target();
        $this->dns([['status' => 'listed', 'response' => '127.0.0.4'], ['status' => 'clean'], ['status' => 'listed', 'response' => '127.0.0.11'], ['status' => 'clean']]);
        app(RblChecker::class)->check($target);
        $this->assertSame('listed', $target->fresh()->last_status);
        $this->assertSame(['203.0.113.0', '203.0.113.1', '203.0.113.2', '203.0.113.3'], $target->checks()->orderBy('id')->pluck('checked_value')->all());
        $this->assertSame('2.113.0.203.rbl0.example.org', $target->checks()->where('checked_value', '203.0.113.2')->first()->query);
        $this->assertSame(2, RblEvent::where('status', 'open')->count());
        $this->travel(2)->minutes();
        $this->dns([['status' => 'clean'], ['status' => 'clean'], ['status' => 'timeout'], ['status' => 'clean']]);
        app(RblChecker::class)->check($target);
        $this->assertDatabaseHas('rbl_events', ['last_checked_value' => '203.0.113.0', 'status' => 'resolved']);
        $this->assertDatabaseHas('rbl_events', ['last_checked_value' => '203.0.113.2', 'status' => 'open']);
        $this->assertSame('listed', $target->fresh()->last_status);
        $this->travel(2)->minutes();
        $this->dns(array_fill(0, 4, ['status' => 'clean']));
        app(RblChecker::class)->check($target);
        $this->assertSame('clean', $target->fresh()->last_status);
        $this->assertSame(0, RblEvent::where('status', 'open')->count());
    }

    public function test_configured_query_budget_is_enforced_and_incomplete_cidr_is_not_clean(): void
    {
        config(['rbl.max_checks_per_target' => 10]);
        $this->lists(2);
        $target = $this->target(['value' => '203.0.113.0/29']);
        $this->dns(array_fill(0, 10, ['status' => 'clean']));
        app(RblChecker::class)->check($target);
        $this->assertSame(10, $target->checks()->where('status', 'clean')->count());
        $this->assertSame(6, $target->checks()->where('status', 'skipped')->count());
        $this->assertSame('skipped', $target->fresh()->last_status);
    }

    public function test_disabled_group_suspends_checks_and_command_limit_is_per_target(): void
    {
        $this->lists();
        $group = RblTargetGroup::create(['name' => 'Pausado', 'slug' => 'pausado', 'enabled' => false]);
        $paused = $this->target(['rbl_target_group_id' => $group->id]);
        $active = $this->target();
        $this->target();
        $this->dns(array_fill(0, 4, ['status' => 'clean']));
        $this->artisan('rbl:check', ['--limit' => 1])->assertSuccessful();
        $this->assertSame(4, $active->checks()->count());
        $this->assertSame(0, $paused->checks()->count());
        $this->assertSame(1, RblRun::first()->targets_checked);
        $this->actingAs(User::factory()->admin()->create())->post('/rbl/targets/'.$paused->id.'/check')->assertSessionHasErrors('check');
    }

    public function test_group_filters_reports_csv_and_events_show_individual_ip(): void
    {
        $this->lists();
        $g = RblTargetGroup::create(['name' => 'Grupo CGNAT', 'slug' => 'cgnat']);
        $t = $this->target(['rbl_target_group_id' => $g->id]);
        $other = $this->target(['name' => 'Outro bloco']);
        $this->dns([['status' => 'clean'], ['status' => 'listed', 'response' => '127.0.0.4'], ['status' => 'clean'], ['status' => 'clean']]);
        app(RblChecker::class)->check($t);
        $this->actingAs(User::factory()->admin()->create());
        $this->get('/rbl?group='.$g->id)->assertOk()->assertViewHas('targets', fn ($targets) => $targets->count() === 1 && $targets->first()->is($t));
        $this->get('/rbl/events?group='.$g->id)->assertOk()->assertSee('Grupo CGNAT')->assertSee('203.0.113.1')->assertViewHas('events', fn ($events) => $events->count() === 1);
        $this->get('/rbl/reports?group='.$g->id)->assertOk()->assertViewHas('summary', fn ($s) => $s['Total de checks'] === 4)->assertViewHas('groupSummary', fn ($s) => count($s) === 1 && $s[0]['Listed'] === 1);
        $csv = $this->get('/rbl/reports?group='.$g->id.'&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Grupo', $csv);
        $this->assertStringContainsString('Grupo CGNAT', $csv);
        $this->assertStringNotContainsString('Outro bloco', $csv);
        $this->get('/rbl/groups/'.$g->id)->assertOk()->assertSee('203.0.113.1');
        $this->get('/rbl/targets/'.$t->id)->assertOk()->assertSee('203.0.113.1');
        $this->get('/rbl/groups/'.$g->id)->assertOk()->assertSee('Resumo dos blocos CGNAT')->assertSee('Total de IPs monitorados')->assertSee('IPs listados')->assertSee('Progresso agregado ponderado');
        $this->get('/rbl/reports?group='.$g->id)->assertOk()->assertSee('Blocos com mais IPs pendentes')->assertSee('Blocos com erro');
    }
}
