<?php

namespace Tests\Feature;

use App\Models\RblAlert;
use App\Models\RblEvent;
use App\Models\RblList;
use App\Models\RblTarget;
use App\Models\RblTargetGroup;
use App\Models\Setting;
use App\Models\User;
use App\Services\Rbl\DnsblResolver;
use App\Services\Rbl\RblAlertService;
use App\Services\Rbl\RblEventService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RblAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rbl.alerts_enabled' => true]);
        Setting::set('telegram_ativo', '1');
        Setting::set('telegram_bot_token', 'secret-test-token');
        Setting::set('telegram_chat_id', '-100123');
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    private function record(string $status, ?RblTarget $target = null, ?RblList $list = null): RblTarget
    {
        $target ??= RblTarget::create(['name' => 'Mail <test>', 'value' => '203.0.113.2', 'type' => 'ip', 'enabled' => true]);
        $list ??= RblList::first() ?? RblList::create(['name' => 'Example ZEN', 'dns_zone' => 'rbl.example.org', 'type' => 'ip', 'enabled' => true]);
        $check = $target->checks()->create([
            'rbl_list_id' => $list->id, 'status' => $status, 'checked_value' => '203.0.113.2',
            'response' => $status === 'listed' ? '127.0.0.4' : null, 'checked_at' => now(),
        ]);
        app(RblEventService::class)->record($check);

        return $target;
    }

    public function test_lifecycle_deduplicates_and_allows_a_new_incident(): void
    {
        $target = $this->record('listed');
        $this->record('listed', $target);
        app(RblAlertService::class)->notify(RblEvent::first(), 'listed');
        Http::assertSentCount(1);
        $this->travel(4)->hours();
        $this->record('clean', $target);
        $this->record('clean', $target);
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => str_contains($r['text'], 'evento resolvido') && str_contains($r['text'], '240 min'));
        $this->assertDatabaseHas('rbl_alerts', ['type' => 'resolved', 'status' => 'sent']);
        $this->record('listed', $target);
        Http::assertSentCount(3);
        $this->assertDatabaseCount('rbl_events', 2);
        $this->assertDatabaseCount('rbl_alerts', 3);
    }

    public function test_cidr_message_contains_individual_ip_group_and_escaped_fields(): void
    {
        $group = RblTargetGroup::create(['name' => 'CGNAT', 'slug' => 'cgnat', 'enabled' => true]);
        $target = RblTarget::create(['name' => 'Bloco <1>', 'value' => '203.0.113.0/30', 'type' => 'cidr', 'enabled' => true, 'rbl_target_group_id' => $group->id]);
        $this->record('listed', $target);
        Http::assertSent(function ($r) {
            foreach (['Bloco &lt;1&gt;', '203.0.113.0/30', 'IP listado: 203.0.113.2', 'Grupo: CGNAT', 'Example ZEN', '127.0.0.4'] as $part) {
                if (! str_contains($r['text'], $part)) {
                    return false;
                }
            }

            return ! str_contains($r['text'], 'secret-test-token');
        });
        $this->assertNotNull(RblAlert::first()->message_hash);
        $this->assertNotNull(RblAlert::first()->sent_at);
    }

    public function test_non_definitive_results_never_open_or_resolve_events(): void
    {
        $target = $this->record('listed');
        foreach (['error', 'timeout', 'skipped'] as $status) {
            $this->record($status, $target);
            $this->record($status);
        }
        Http::assertSentCount(1);
        $this->assertDatabaseCount('rbl_events', 1);
        $this->assertDatabaseHas('rbl_events', ['status' => 'open']);
    }

    public function test_disabled_and_unconfigured_alerts_are_audited_without_backfill(): void
    {
        config(['rbl.alerts_enabled' => false]);
        $target = $this->record('listed');
        config(['rbl.alerts_enabled' => true]);
        $this->record('listed', $target);
        config(['rbl.alert_on_resolved' => false]);
        $this->record('clean', $target);
        config(['rbl.alert_on_listed' => false]);
        $this->record('listed');
        config(['rbl.alert_on_listed' => true, 'rbl.alert_channels' => []]);
        $this->record('listed');
        config(['rbl.alert_channels' => ['telegram']]);
        Setting::set('telegram_ativo', '0');
        $this->record('listed');
        Setting::set('telegram_ativo', '1');
        Setting::set('telegram_bot_token', '');
        config(['services.telegram.bot_token' => null]);
        $this->record('listed');
        Http::assertNothingSent();
        $this->assertSame(6, RblAlert::where('status', 'skipped')->count());
    }

    public function test_message_options_omit_group_and_codes(): void
    {
        config(['rbl.include_group' => false, 'rbl.include_response_codes' => false]);
        $this->record('listed');
        Http::assertSent(fn ($r) => ! str_contains($r['text'], 'Resposta:') && ! str_contains($r['text'], 'Grupo:'));
    }

    public function test_transport_exception_is_sanitized_and_does_not_fail_command(): void
    {
        Log::spy();
        Http::fake(fn () => throw new \RuntimeException('https://api.telegram.org/botsecret-test-token/sendMessage'));
        RblTarget::create(['name' => 'Mail', 'value' => '203.0.113.2', 'type' => 'ip', 'enabled' => true]);
        RblList::create(['name' => 'ZEN', 'dns_zone' => 'rbl.example.org', 'type' => 'ip', 'enabled' => true]);
        $mock = $this->mock(DnsblResolver::class);
        $mock->shouldReceive('queryFor')->once()->andReturn('2.113.0.203.rbl.example.org');
        $mock->shouldReceive('resolve')->once()->andReturn(['status' => 'listed', 'response' => '127.0.0.4']);
        $this->artisan('rbl:check')->expectsOutput('Eventos novos: 1; eventos resolvidos: 0; alertas enviados: 0; alertas falhos: 1.')->assertSuccessful();
        $this->assertDatabaseHas('rbl_runs', ['status' => 'completed', 'error_count' => 0]);
        $this->assertDatabaseHas('rbl_alerts', ['status' => 'failed']);
        $this->assertStringNotContainsString('secret-test-token', RblAlert::first()->toJson());
        Log::shouldHaveReceived('warning')->with('Exceção ao enviar notificação Telegram', ['erro' => 'Falha de comunicação com Telegram.'])->once();
        $this->actingAs(User::factory()->admin()->create())->get('/rbl/events')->assertOk()->assertSee('Alerta falhou')->assertDontSee('secret-test-token');
    }

    public function test_api_errors_are_failed_without_logging_response_secrets(): void
    {
        Log::spy();
        foreach ([200, 429, 500] as $status) {
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(fn () => Http::response(['ok' => false, 'description' => 'secret-test-token'], $status));
            $this->record('listed');
            Log::shouldHaveReceived('warning')->with('Falha ao enviar notificação Telegram', ['status' => $status])->once();
        }
        $this->assertSame(3, RblAlert::where('status', 'failed')->count());
        $this->assertStringNotContainsString('secret-test-token', RblAlert::all()->toJson());
    }

    public function test_rollback_never_sends_alert(): void
    {
        DB::beginTransaction();
        $this->record('listed');
        Http::assertNothingSent();
        DB::rollBack();
        Http::assertNothingSent();
        $this->assertDatabaseCount('rbl_alerts', 0);
    }

    public function test_database_enforces_unique_transition_channel(): void
    {
        $this->record('listed');
        $this->expectException(UniqueConstraintViolationException::class);
        RblAlert::create(['rbl_event_id' => RblEvent::first()->id, 'type' => 'listed', 'channel' => 'telegram', 'status' => 'sent']);
    }

    public function test_ui_reports_filter_alerts_by_period_and_group(): void
    {
        $target = $this->record('listed');
        $this->record('clean', $target);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/rbl/events')->assertOk()->assertSee('Alerta enviado')->assertSee('telegram')->assertDontSee('secret-test-token');
        $this->get('/rbl/reports')->assertOk()->assertViewHas('alertSummary', fn ($s) => $s['Alertas enviados'] === 2 && $s['Eventos resolved com alerta enviado'] === 1);
        $group = RblTargetGroup::create(['name' => 'Outro', 'slug' => 'outro', 'enabled' => true]);
        $this->get('/rbl/reports?group='.$group->id)->assertOk()->assertViewHas('alertSummary', fn ($s) => $s['Alertas enviados'] === 0);
        $this->get('/rbl/reports?start=2000-01-01&end=2000-01-02')->assertOk()->assertViewHas('alertSummary', fn ($s) => $s['Alertas enviados'] === 0);
    }
}
