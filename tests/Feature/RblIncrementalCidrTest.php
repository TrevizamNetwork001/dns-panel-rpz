<?php

namespace Tests\Feature;

use App\Models\RblEvent;
use App\Models\RblList;
use App\Models\RblTarget;
use App\Models\User;
use App\Services\Rbl\DnsblResolver;
use App\Services\Rbl\RblChecker;
use App\Services\Rbl\TargetExpansion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RblIncrementalCidrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rbl.alerts_enabled' => false, 'rbl.large_cidr_enabled' => true, 'rbl.max_cidr_total_ips' => 1024,
            'rbl.min_cidr_prefix' => 22, 'rbl.batch_ips_per_run' => 16, 'rbl.max_checks_per_target' => 100]);
        RblList::create(['name' => 'RBL', 'dns_zone' => 'rbl.example.org', 'type' => 'ip', 'enabled' => true, 'timeout_seconds' => 1]);
    }

    private function target(string $value = '203.0.113.0/24'): RblTarget
    {
        return RblTarget::create(['name' => 'Bloco CGNAT', 'type' => 'cidr', 'value' => $value, 'enabled' => true]);
    }

    private function cleanDns(int $times): void
    {
        $mock = $this->mock(DnsblResolver::class);
        $mock->shouldReceive('queryFor')->times($times)->andReturnUsing(fn ($ip, $zone) => implode('.', array_reverse(explode('.', $ip))).'.'.$zone);
        $mock->shouldReceive('resolve')->times($times)->andReturn(['status' => 'clean']);
    }

    public function test_default_limits_accept_slash_24_and_reject_slash_21(): void
    {
        $this->assertSame(1024, config('rbl.max_cidr_total_ips'));
        $this->assertSame(16, config('rbl.batch_ips_per_run'));
        $this->assertCount(16, app(TargetExpansion::class)->plan($this->target())['ips']);
        $plan = app(TargetExpansion::class)->plan($this->target('198.51.100.0/21'));
        $this->assertSame([], $plan['ips']);
        $this->assertStringContainsString('limite incremental', $plan['reason']);
    }

    public function test_batch_advances_cursor_and_dry_run_does_not_write(): void
    {
        $target = $this->target();
        $this->actingAs(User::factory()->admin()->create())->artisan('rbl:check', ['--target' => $target->id, '--dry-run' => true])
            ->expectsOutputToContain('Total de IPs: 256')->expectsOutputToContain('IPs planejados: 16')->assertSuccessful();
        $this->assertNull($target->fresh()->scanState);
        $this->assertDatabaseCount('rbl_checks', 0);
        $this->cleanDns(16);
        app(RblChecker::class)->check($target);
        $state = $target->fresh()->scanState;
        $this->assertSame(16, $state->cursor);
        $this->assertSame(16, $state->scanned_ips);
        $this->assertSame('partial', $target->fresh()->last_status);
        $this->assertSame('203.0.113.15', $target->checks()->latest('id')->first()->checked_value);
    }

    public function test_cursor_restarts_and_clean_only_after_complete_cycle(): void
    {
        config(['rbl.max_cidr_ips' => 1, 'rbl.batch_ips_per_run' => 2]);
        $target = $this->target('203.0.113.0/30');
        $this->cleanDns(2);
        app(RblChecker::class)->check($target);
        $this->assertSame('partial', $target->fresh()->last_status);
        $this->travel(2)->minutes();
        $this->cleanDns(2);
        app(RblChecker::class)->check($target);
        $state = $target->fresh()->scanState;
        $this->assertSame(0, $state->cursor);
        $this->assertSame(1, $state->cycle);
        $this->assertNotNull($state->completed_at);
        $this->assertSame('clean', $target->fresh()->last_status);
        $this->travel(2)->minutes();
        $this->cleanDns(2);
        app(RblChecker::class)->check($target);
        $this->assertSame(2, $target->fresh()->scanState->cycle);
        $this->assertSame(2, $target->fresh()->scanState->cursor);
        $this->assertSame('partial', $target->fresh()->last_status);
    }

    public function test_distinct_ips_create_distinct_events_and_listed_wins(): void
    {
        config(['rbl.max_cidr_ips' => 1, 'rbl.batch_ips_per_run' => 2]);
        $target = $this->target('203.0.113.0/30');
        $mock = $this->mock(DnsblResolver::class);
        $mock->shouldReceive('queryFor')->twice()->andReturnUsing(fn ($ip, $zone) => implode('.', array_reverse(explode('.', $ip))).'.'.$zone);
        $mock->shouldReceive('resolve')->twice()->andReturn(['status' => 'listed', 'response' => '127.0.0.2']);
        app(RblChecker::class)->check($target);
        $this->assertSame('listed', $target->fresh()->last_status);
        $this->assertSame(2, RblEvent::where('status', 'open')->distinct()->count('last_checked_value'));
    }

    public function test_admin_pages_and_csv_show_block_progress(): void
    {
        $target = $this->target();
        $this->cleanDns(16);
        app(RblChecker::class)->check($target);
        $this->actingAs(User::factory()->admin()->create())->get('/rbl')->assertOk()->assertSee('16/256')->assertSee('Parcial');
        $this->get(route('rbl.targets.show', $target))->assertOk()->assertSee('Progresso do bloco CGNAT')->assertSee('Pendentes estimados')->assertSee('240');
        $this->get('/rbl/reports')->assertOk()->assertSee('Resumo de blocos CIDR')->assertSee('Total de IPs nos blocos');
        $csv = $this->get('/rbl/reports?format=csv')->assertOk()->streamedContent();
        foreach (['target_type', 'target_value', 'checked_value', 'scan_cycle', 'block_progress_percent', 'aggregate_status'] as $header) {
            $this->assertStringContainsString($header, $csv);
        }
    }

    public function test_cidr_detail_shows_next_batch_open_listed_ips_and_recent_checks(): void
    {
        $target = $this->target();
        $target->update(['category' => 'cgnat']);
        $mock = $this->mock(DnsblResolver::class);
        $mock->shouldReceive('queryFor')->times(16)->andReturnUsing(fn ($ip, $zone) => implode('.', array_reverse(explode('.', $ip))).'.'.$zone);
        $mock->shouldReceive('resolve')->times(16)->andReturn(
            ['status' => 'listed', 'response' => '127.0.0.2'],
            ...array_fill(0, 15, ['status' => 'clean'])
        );
        app(RblChecker::class)->check($target);

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('rbl.targets.show', $target))->assertOk()
            ->assertSee('Listado')->assertSee('Há pelo menos um IP com evento aberto.')
            ->assertSee('Próximo lote planejado')->assertSee('16 IPs')->assertSee('16 checks planejados')
            ->assertSee('203.0.113.16')->assertSee('IPs listados neste bloco')->assertSee('203.0.113.0')
            ->assertSee('Ver evento')->assertSee('Últimos IPs verificados')
            ->assertSee('Verificar agora — próximo lote');
        $this->get('/rbl')->assertOk()->assertSee('CGNAT')->assertSee('Listado')->assertSee('P 240');
    }
}
