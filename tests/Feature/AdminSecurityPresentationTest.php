<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSecurityPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function audit(string $action, string $date, string $ip = '107.173.160.167'): AuditLog
    {
        return AuditLog::create([
            'action' => $action,
            'description' => $action === 'auth.login_failed' ? 'Tentativa de login falhou para alvo@example.com' : 'Estado de saúde registrado',
            'ip_address' => $ip,
            'created_at' => $date,
        ]);
    }

    private function ban(string $action, string $ip, string $date): void
    {
        DB::table('security_bans')->insert([
            'action' => $action, 'ip_address' => $ip, 'jail' => 'sshd', 'created_at' => $date,
        ]);
    }

    public function test_empty_state_metrics_and_compact_ssh_note(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('seguranca.index'));

        $response->assertOk()->assertSeeText('IPs bloqueados')
            ->assertSeeText('bloqueios nas últimas 24 h')->assertSeeText('falhas de login nas últimas 24 h')
            ->assertSeeText('Saúde')->assertSeeText('Sem dados')->assertSeeText('ainda não rodou')
            ->assertSeeText('Proteção SSH')->assertSeeText('fail2ban')
            ->assertSeeText('5 tentativas de senha erradas em 10 minutos')
            ->assertSeeText('bloqueio automático por 1 hora')->assertSeeText('Esta tela apenas exibe o estado')
            ->assertSeeText('não é um firewall configurável por aqui')
            ->assertSeeText('Nenhum IP bloqueado no momento.')->assertSeeText('nenhuma ameaça ativa')
            ->assertSeeText('Nenhuma falha de login recente.')->assertSeeText('Nenhum evento de bloqueio registrado.')
            ->assertDontSee('<table', false)->assertDontSee('alert-error')
            ->assertViewHas('bansUltimas24h', 0)->assertViewHas('loginFalhasUltimas24h', 0);
        $this->assertSame(4, substr_count($response->getContent(), 'class="metric-card"'));
        $this->assertCount(0, $response->viewData('bansAtivos'));
    }

    public function test_metrics_keep_real_24_hour_boundaries_and_existing_health_state(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->ban('ban', '192.0.2.1', now()->subHours(25)->toDateTimeString());
        $this->ban('ban', '192.0.2.2', now()->subHours(24)->toDateTimeString());
        $this->ban('unban', '192.0.2.2', now()->subHour()->toDateTimeString());
        $this->ban('ban', '2001:db8::1', now()->toDateTimeString());
        $this->audit('auth.login_failed', now()->subHours(24)->subSecond()->toDateTimeString());
        $this->audit('auth.login_failed', now()->subHours(24)->toDateTimeString());
        $this->audit('auth.login_failed', now()->toDateTimeString());
        $this->audit('auth.login', now()->toDateTimeString());
        $this->audit('health.ok', now()->toDateTimeString());

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('seguranca.index'));

        $response->assertOk()->assertViewHas('bansUltimas24h', 2)->assertViewHas('loginFalhasUltimas24h', 2)
            ->assertSeeText('OK')->assertSeeText('última checagem:');
        $this->assertSame(['2001:db8::1', '192.0.2.1'], $response->viewData('bansAtivos')->pluck('ip_address')->all());
        $this->assertCount(3, $response->viewData('ultimasFalhasLogin'));
        $this->assertCount(0, $response->viewData('alertasSaude'));
        $this->assertDatabaseCount('audit_logs', 5);
        $this->assertDatabaseCount('security_bans', 4);
    }

    public function test_streams_preserve_ips_details_jail_actions_repetitions_and_order(): void
    {
        $this->ban('ban', '45.182.96.18', '2026-09-01 17:20:41');
        $this->ban('unban', '45.182.96.18', '2026-09-01 18:20:39');
        $this->ban('ban', '2001:db8::abcd', '2026-09-01 19:20:39');
        $this->audit('auth.login_failed', '2026-09-06 14:48:34');
        $this->audit('auth.login_failed', '2026-09-06 14:49:34');
        $this->audit('auth.login_failed', '2026-09-06 14:50:34', '2001:db8::abcd');
        $this->audit('health.site_down', now()->toDateTimeString());
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('seguranca.index'));

        $response->assertOk()->assertDontSee('<table', false)->assertDontSee('status-pill')
            ->assertSeeText('107.173.160.167')->assertSeeText('2001:db8::abcd')
            ->assertSeeText('alvo@example.com')->assertSeeText('sshd')
            ->assertSee('title="Tentativa de login falhou para alvo@example.com"', false)
            ->assertSeeText('Bloqueado')->assertSeeText('Desbloqueado')
            ->assertSeeText('requer atenção')->assertSeeText('Estado de saúde registrado');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['IPs bloqueados' => 1, 'Falhas de login' => 3, 'Histórico SSH' => 3, 'Alertas de saúde' => 1] as $label => $count) {
            $this->assertCount($count, $xpath->query('//ul[@aria-label="'.$label.'"]/li'));
        }
        $history = $xpath->query('//ul[@aria-label="Histórico SSH"]/li');
        $this->assertStringContainsString('01/09/2026 19:20:39', $history[0]->textContent);
        $this->assertStringContainsString('security-event-danger', $history[0]->getAttribute('class'));
        $this->assertStringContainsString('Desbloqueado', $history[1]->textContent);
        $this->assertStringContainsString('security-event-success', $history[1]->getAttribute('class'));
        $failures = $xpath->query('//ul[@aria-label="Falhas de login"]/li');
        foreach ($failures as $failure) {
            $this->assertStringContainsString('Falha de login', $failure->textContent);
            $this->assertStringContainsString('alvo@example.com', $failure->textContent);
        }
        $this->assertStringContainsString('14:50:34', $failures[0]->textContent);
        $this->assertStringContainsString('14:48:34', $failures[2]->textContent);
    }

    public function test_existing_limits_and_query_count_do_not_grow_per_item(): void
    {
        $admin = User::factory()->admin()->create();
        $addEvents = function (int $start, int $end): void {
            foreach (range($start, $end) as $index) {
                $this->ban('ban', '2001:db8::'.dechex($index), now()->toDateTimeString());
                $this->audit('auth.login_failed', now()->toDateTimeString());
                $this->audit('health.disk_low', now()->toDateTimeString());
            }
        };
        $addEvents(1, 1);
        $this->actingAs($admin)->get(route('seguranca.index'))->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('seguranca.index'))->assertOk();
        $smallCount = count(DB::getQueryLog());
        $addEvents(2, 205);
        DB::flushQueryLog();
        $response = $this->get(route('seguranca.index'))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame($smallCount, count($queries));
        $this->assertLessThan(10, count($queries));
        $this->assertCount(1, array_filter($queries, fn ($q) => str_contains($q['query'], 'from "security_bans"')));
        $this->assertCount(200, $response->viewData('bansAtivos'));
        $this->assertSame(range(205, 156), $response->viewData('historico')->pluck('id')->all());
        $this->assertSame(range(409, 381, -2), $response->viewData('ultimasFalhasLogin')->pluck('id')->all());
        $this->assertCount(15, $response->viewData('alertasSaude'));
        $response->assertViewHas('bansUltimas24h', 200)->assertViewHas('loginFalhasUltimas24h', 205);
        $this->assertDatabaseCount('audit_logs', 410);
        $this->assertDatabaseCount('security_bans', 205);
    }

    public function test_admin_middleware_and_client_and_guest_access_are_preserved(): void
    {
        $route = app('router')->getRoutes()->getByName('seguranca.index');
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('admin', $route->gatherMiddleware());
        $this->get(route('seguranca.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->cliente()->create())->get(route('seguranca.index'))->assertForbidden();
    }
}
