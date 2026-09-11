<?php

namespace Tests\Feature;

use App\Models\RblAlert;
use App\Models\RblEvent;
use App\Models\RblList;
use App\Models\RblRun;
use App\Models\RblTarget;
use App\Models\User;
use App\Services\Rbl\DnsblResolver;
use App\Services\Rbl\RblChecker;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RblMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function target(array $data = []): RblTarget
    {
        return RblTarget::create(array_merge(['name' => 'Mail', 'type' => 'ip', 'value' => '1.2.3.4', 'enabled' => true], $data));
    }

    private function rbl(array $data = []): RblList
    {
        return RblList::create(array_merge(['name' => 'Example', 'type' => 'ip', 'dns_zone' => 'rbl.example.org', 'enabled' => true], $data));
    }

    private function dns(array $statuses): void
    {
        $mock = $this->mock(DnsblResolver::class);
        $mock->shouldReceive('queryFor')->andReturn('4.3.2.1.rbl.example.org');
        $mock->shouldReceive('resolve')->times(count($statuses))->andReturn(...array_map(fn ($status) => ['status' => $status, 'response' => $status === 'listed' ? '127.0.0.2' : null], $statuses));
    }

    public function test_command_processes_enabled_targets_and_records_all_counts(): void
    {
        $this->rbl();
        foreach (range(1, 5) as $i) {
            $this->target(['name' => "Target $i"]);
        }
        $disabled = $this->target(['enabled' => false]);
        $this->dns(['clean', 'listed', 'skipped', 'error', 'timeout']);
        $this->artisan('rbl:check')->assertSuccessful();
        $this->assertDatabaseHas('rbl_runs', ['status' => 'completed', 'targets_checked' => 5, 'checks_created' => 5, 'clean_count' => 1, 'listed_count' => 1, 'skipped_count' => 1, 'error_count' => 2]);
        $this->assertSame(0, $disabled->checks()->count());
        $this->assertSame(5, RblRun::first()->checks()->count());
        $this->assertNotNull(RblRun::first()->finished_at);
        $this->assertNotNull(RblRun::first()->duration_ms);
    }

    public function test_target_option_and_disabled_target(): void
    {
        $this->rbl();
        $other = $this->target();
        $selected = $this->target();
        $disabled = $this->target(['enabled' => false]);
        $this->dns(['clean']);
        $this->artisan('rbl:check', ['--target' => $selected->id, '--only-enabled' => true])->assertSuccessful();
        $this->artisan('rbl:check', ['--target' => $disabled->id])
            ->expectsOutput('O alvo RBL informado está desativado.')
            ->assertFailed();
        $this->artisan('rbl:check', ['--target' => 999999])
            ->expectsOutput('Alvo RBL não encontrado.')
            ->assertFailed();
        $this->assertSame(0, $other->checks()->count());
        $this->assertSame(1, $selected->checks()->count());
        $this->assertSame(0, $disabled->checks()->count());
    }

    public function test_limit_rotates_targets_and_respects_cooldown(): void
    {
        $this->rbl();
        $recent = $this->target(['last_checked_at' => now()]);
        $old = $this->target(['last_checked_at' => now()->subDay()]);
        $never = $this->target();
        $this->dns(['clean', 'clean']);
        $this->artisan('rbl:check', ['--limit' => 1])->assertSuccessful();
        $this->assertSame(1, $never->checks()->count());
        $this->artisan('rbl:check', ['--limit' => 1])->assertSuccessful();
        $this->assertSame(1, $old->checks()->count());
        $this->assertSame(0, $recent->checks()->count());
    }

    public function test_dry_run_and_invalid_options_do_not_write_or_query_dns(): void
    {
        $this->target();
        $this->mock(DnsblResolver::class)->shouldNotReceive('resolve');
        $this->artisan('rbl:check', ['--dry-run' => true])->expectsOutput('Simulação: 1 alvo(s) elegível(is).')->assertSuccessful();
        foreach ([['--limit' => 0], ['--limit' => 1001], ['--target' => 'abc']] as $options) {
            $this->artisan('rbl:check', $options)->assertFailed();
        }
        $this->assertDatabaseCount('rbl_runs', 0);
        $this->assertDatabaseCount('rbl_checks', 0);
    }

    public function test_unsupported_targets_are_skipped_and_disabled_lists_ignored(): void
    {
        $this->rbl();
        $this->rbl(['dns_zone' => 'disabled.example.org', 'enabled' => false]);
        foreach (['cidr' => '1.2.0.0/21', 'domain' => 'example.org', 'hostname' => 'mail.example.org', 'ip' => '2001:db8::1'] as $type => $value) {
            $this->target(compact('type', 'value'));
        }
        $this->mock(DnsblResolver::class)->shouldNotReceive('resolve');
        $this->artisan('rbl:check')->assertSuccessful();
        $this->assertDatabaseHas('rbl_runs', ['status' => 'completed', 'skipped_count' => 4, 'checks_created' => 4]);
    }

    public function test_structural_failure_is_sanitized_and_preserves_partial_counts(): void
    {
        $target = $this->target();
        $this->target();
        $list = $this->rbl();
        $mock = $this->mock(RblChecker::class);
        $mock->shouldReceive('check')->once()->ordered()->andReturnUsing(function ($target, $runId) use ($list) {
            $target->checks()->create(['rbl_run_id' => $runId, 'rbl_list_id' => $list->id, 'checked_value' => $target->value, 'status' => 'clean', 'checked_at' => now()]);

            return $target;
        });
        $mock->shouldReceive('check')->once()->ordered()->andThrow(new \RuntimeException('secret credential'));
        $this->artisan('rbl:check')->expectsOutput('Falha estrutural na execução RBL. Verifique banco, cache e listas ativas.')->assertFailed();
        $this->assertDatabaseHas('rbl_runs', ['status' => 'failed', 'targets_checked' => 1, 'checks_created' => 1, 'clean_count' => 1, 'error_message' => 'Falha estrutural na execução RBL.']);
        $this->assertNotNull(RblRun::first()->finished_at);
        $this->assertTrue(Cache::lock('rbl:scheduled-run', 1)->get());
    }

    public function test_no_active_lists_is_a_recorded_failure(): void
    {
        $this->target();
        $this->artisan('rbl:check')->assertFailed();
        $this->assertDatabaseHas('rbl_runs', ['status' => 'failed', 'checks_created' => 0]);
    }

    public function test_concurrent_command_is_a_noop(): void
    {
        $lock = Cache::lock('rbl:scheduled-run', 60);
        $lock->get();
        $this->artisan('rbl:check')->assertSuccessful();
        $this->assertDatabaseCount('rbl_runs', 0);
        $this->assertFalse(Cache::lock('rbl:scheduled-run', 60)->get());
        $lock->release();
    }

    public function test_scheduler_is_conservative_and_prevents_overlap(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'rbl:check'));
        $this->assertNotNull($event);
        $this->assertSame('0 */6 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_every_rbl_route_has_web_auth_and_admin_middleware(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'rbl.'));

        $this->assertCount(26, $routes);
        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('web', $middleware, $route->getName());
            $this->assertContains('auth', $middleware, $route->getName());
            $this->assertContains('admin', $middleware, $route->getName());
        }
    }

    public function test_command_event_lifecycle_dashboard_and_duration(): void
    {
        $this->target(['name' => 'Listed mail']);
        $this->rbl();
        $this->dns(['listed', 'listed', 'clean']);
        $this->artisan('rbl:check')->assertSuccessful();
        $this->actingAs(User::factory()->admin()->create())->get('/rbl')->assertOk()->assertSee('Listed mail')->assertViewHas('open', 1);
        $this->travel(2)->minutes();
        $this->artisan('rbl:check')->assertSuccessful();
        $this->assertDatabaseCount('rbl_events', 1);
        $this->travel(2)->minutes();
        $this->artisan('rbl:check')->assertSuccessful();
        $this->assertSame(4, RblEvent::first()->durationMinutes());
        $this->get('/rbl')->assertOk()->assertViewHas('resolved24h', 1);
        $this->get('/rbl/reports')->assertOk()->assertViewHas('summary', fn ($summary) => $summary['Eventos resolvidos no período'] === 1);
        $this->get('/rbl/events?status=resolved')->assertOk()->assertSee('4 min');
    }

    public function test_reports_count_inclusive_dates_and_rank_listed_checks(): void
    {
        $target = $this->target();
        $list = $this->rbl();
        foreach (['clean', 'listed', 'skipped', 'error', 'timeout'] as $status) {
            $target->checks()->create(['rbl_list_id' => $list->id, 'checked_value' => $target->value, 'status' => $status, 'checked_at' => '2026-08-10 23:59:59']);
        }
        foreach (['2026-08-09 23:59:59', '2026-08-11 00:00:00'] as $date) {
            $target->checks()->create(['rbl_list_id' => $list->id, 'checked_value' => $target->value, 'status' => 'listed', 'checked_at' => $date]);
        }
        RblEvent::create(['rbl_target_id' => $target->id, 'rbl_list_id' => $list->id, 'status' => 'resolved', 'first_seen_at' => '2026-08-09', 'last_seen_at' => '2026-08-09', 'resolved_at' => '2026-08-10 23:59:59']);
        $this->actingAs(User::factory()->admin()->create())->get('/rbl/reports?start=2026-08-10&end=2026-08-10')
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['Total de checks'] === 5 && $summary['Eventos resolvidos no período'] === 1)
            ->assertViewHas('topTargets', fn ($items) => $items->first()->total === 1)
            ->assertViewHas('topLists', fn ($items) => $items->first()->total === 1);
    }

    public function test_event_filters_include_old_open_events_and_overlap(): void
    {
        $target = $this->target();
        $other = $this->target();
        $list = $this->rbl();
        foreach ([$target, $other] as $item) {
            RblEvent::create(['rbl_target_id' => $item->id, 'rbl_list_id' => $list->id, 'status' => 'open', 'first_seen_at' => '2026-01-01', 'last_seen_at' => '2026-01-01']);
        }
        $this->actingAs(User::factory()->admin()->create())->get("/rbl/events?start=2026-08-10&end=2026-08-10&status=open&target={$target->id}&list={$list->id}")
            ->assertOk()->assertViewHas('events', fn ($events) => $events->total() === 1 && $events->first()->rbl_target_id === $target->id);
        $this->get('/rbl/events?status=resolved')->assertOk()->assertViewHas('events', fn ($events) => $events->total() === 0);
    }

    public function test_csv_streams_filtered_checks_and_neutralizes_formulas(): void
    {
        $target = $this->target(['name' => "\t=HYPERLINK(\"evil\")"]);
        $list = $this->rbl();
        $target->checks()->create(['rbl_list_id' => $list->id, 'checked_value' => '1.2.3.4', 'status' => 'listed', 'checked_at' => '2026-08-10', 'response' => '+formula']);
        $target->checks()->create(['rbl_list_id' => $list->id, 'checked_value' => 'excluded', 'status' => 'listed', 'checked_at' => '2026-08-11']);
        $response = $this->actingAs(User::factory()->admin()->create())->get('/rbl/reports?start=2026-08-10&end=2026-08-10&format=csv')
            ->assertOk()->assertDownload('rbl-report-2026-08-10-to-2026-08-10.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("' =HYPERLINK", $csv);
        $this->assertStringContainsString("'+formula", $csv);
        $this->assertStringContainsString('1.2.3.4', $csv);
        $this->assertStringNotContainsString('excluded', $csv);
    }

    public function test_monitoring_routes_require_admin_and_validate_filters(): void
    {
        foreach (['/rbl/events', '/rbl/reports', '/rbl/reports?format=csv'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->actingAs(User::factory()->cliente()->create());
        foreach (['/rbl/events', '/rbl/reports', '/rbl/reports?format=csv'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->actingAs(User::factory()->admin()->create());
        foreach (['/rbl/events', '/rbl/reports'] as $url) {
            $this->getJson($url.'?start=2026-08-11&end=2026-08-10')->assertUnprocessable();
            $this->getJson($url.'?start=garbage')->assertUnprocessable();
        }
        $this->getJson('/rbl/events?status=invalid')->assertUnprocessable();
        $this->getJson('/rbl/events?target=999')->assertUnprocessable();
    }

    public function test_event_investigation_detail_timeline_report_and_audit(): void
    {
        $target = $this->target(['name' => 'CIDR mail', 'type' => 'cidr', 'value' => '203.0.113.0/30']);
        $list = $this->rbl();
        $event = RblEvent::create(['rbl_target_id' => $target->id, 'rbl_list_id' => $list->id, 'last_checked_value' => '203.0.113.2', 'status' => 'open', 'first_seen_at' => now()->subHour(), 'last_seen_at' => now()->subMinutes(10), 'last_response' => '127.0.0.2']);
        $target->checks()->create(['rbl_list_id' => $list->id, 'checked_value' => '203.0.113.2', 'status' => 'listed', 'checked_at' => now()->subHour(), 'response' => '127.0.0.2']);
        $target->checks()->create(['rbl_list_id' => $list->id, 'checked_value' => '203.0.113.2', 'status' => 'clean', 'checked_at' => now()->subMinutes(5)]);
        RblAlert::create(['rbl_event_id' => $event->id, 'type' => 'listed', 'channel' => 'telegram', 'status' => 'failed', 'failure_reason' => 'Falha no envio.', 'message_hash' => str_repeat('a', 64)]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('rbl.events.show', $event))->assertOk()->assertSee('203.0.113.2')->assertSee('Check LISTED')->assertSee('Check CLEAN')->assertSee('Alerta listed failed');
        $this->patch(route('rbl.events.investigation.update', $event), ['investigation_status' => 'investigating', 'operator_notes' => '=observação operacional'])->assertRedirect();
        $this->assertDatabaseHas('rbl_events', ['id' => $event->id, 'status' => 'open', 'resolved_at' => null, 'investigation_status' => 'investigating', 'operator_notes' => '=observação operacional']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rbl.event.marked_investigating', 'target_type' => 'rbl_event', 'target_id' => $event->id]);

        foreach (['investigated', 'false_positive'] as $status) {
            $this->patch(route('rbl.events.investigation.update', $event), ['investigation_status' => $status])->assertRedirect();
            $this->assertDatabaseHas('rbl_events', ['id' => $event->id, 'status' => 'open', 'investigation_status' => $status, 'investigated_by' => $admin->id]);
        }
        $this->get(route('rbl.events.report', $event))->assertOk()->assertSee('Relatório do incidente')->assertSee('observação operacional')->assertDontSee('secret-never-visible');
    }

    public function test_event_routes_and_actions_require_admin(): void
    {
        $event = RblEvent::create(['rbl_target_id' => $this->target()->id, 'rbl_list_id' => $this->rbl()->id, 'status' => 'open', 'first_seen_at' => now(), 'last_seen_at' => now()]);
        foreach ([route('rbl.events.show', $event), route('rbl.events.report', $event)] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post(route('rbl.events.investigation.update', $event), ['_method' => 'PATCH', 'investigation_status' => 'investigating'])->assertRedirect('/login');
        $this->actingAs(User::factory()->cliente()->create());
        $this->get(route('rbl.events.show', $event))->assertForbidden();
        $this->get(route('rbl.events.report', $event))->assertForbidden();
        $this->patch(route('rbl.events.investigation.update', $event), ['investigation_status' => 'investigating'])->assertForbidden();
    }

    public function test_recurrence_filter_reports_and_csv_operational_fields(): void
    {
        $target = $this->target();
        $list = $this->rbl();
        foreach ([now()->subDays(2), now()->subDay(), now()] as $date) {
            RblEvent::create(['rbl_target_id' => $target->id, 'rbl_list_id' => $list->id, 'last_checked_value' => '1.2.3.4', 'status' => 'resolved', 'investigation_status' => 'investigated', 'operator_notes' => '=nota', 'first_seen_at' => $date, 'last_seen_at' => $date, 'resolved_at' => $date]);
        }
        $admin = User::factory()->admin()->create();
        $event = RblEvent::latest('id')->first();
        $this->actingAs($admin)->get(route('rbl.events.show', $event))->assertOk()->assertSee('Eventos anteriores do mesmo alvo + RBL')->assertSee('<strong>2</strong>', false);
        $this->get('/rbl/events?investigation_status=investigated')->assertOk()->assertViewHas('events', fn ($events) => $events->total() === 3);
        $this->get('/rbl/reports')->assertOk()->assertViewHas('recurringTargets', fn ($items) => $items->first()->total === 3)->assertViewHas('recurringValues', fn ($items) => $items->first()->total === 3);
        $csv = $this->get('/rbl/reports?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Status investigação', $csv);
        $this->assertStringContainsString('IP afetado', $csv);
        $this->assertStringContainsString("'=nota", $csv);
    }
}
