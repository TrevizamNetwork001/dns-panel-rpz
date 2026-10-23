<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\Servidor;
use App\Models\User;
use App\Services\RpzDirtyMarker;
use App\Services\RpzZoneBuildService;
use App\Services\SqliteDomainFeedWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EcosystemCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bans_and_daily_count_survive_more_than_200_unrelated_events(): void
    {
        config(['security.fail2ban_snapshot' => '/nonexistent-snapshot']);
        DB::table('security_bans')->insert(['ip_address' => '192.0.2.200', 'jail' => 'sshd', 'action' => 'ban', 'created_at' => now()]);
        for ($i = 1; $i <= 205; $i++) {
            DB::table('security_bans')->insert(['ip_address' => '2001:db8::'.dechex($i), 'jail' => 'panel-login', 'action' => 'unban', 'created_at' => now()]);
        }
        $this->actingAs(User::factory()->admin()->create())->get(route('seguranca.index'))->assertOk()
            ->assertViewHas('bansUltimas24h', 1)
            ->assertViewHas('bansAtivos', fn ($bans) => $bans->count() === 1 && $bans[0]->ip_address === '192.0.2.200');
    }

    public function test_fresh_firewall_snapshot_overrides_missing_hook_events_and_stale_snapshot_does_not(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'f2b-test-');
        config(['security.fail2ban_snapshot' => $path]);
        try {
            DB::table('security_bans')->insert(['ip_address' => '192.0.2.1', 'jail' => 'sshd', 'action' => 'ban', 'created_at' => now()]);
            file_put_contents($path, json_encode(['generated_at' => time(), 'bans' => [['ip_address' => '2001:db8::2', 'jail' => 'panel-login']]]));
            $this->actingAs(User::factory()->admin()->create())->get(route('seguranca.index'))->assertOk()
                ->assertViewHas('estadoConfirmado', true)
                ->assertViewHas('bansAtivos', fn ($bans) => $bans->count() === 1 && $bans[0]->ip_address === '2001:db8::2');
            file_put_contents($path, json_encode(['generated_at' => time() - 600, 'bans' => []]));
            $this->get(route('seguranca.index'))->assertOk()->assertViewHas('estadoConfirmado', false)
                ->assertViewHas('bansAtivos', fn ($bans) => $bans[0]->ip_address === '192.0.2.1');
        } finally {
            unlink($path);
        }
    }

    public function test_company_redirect_matches_dynamic_and_published_zone_and_returns_to_nxdomain(): void
    {
        Queue::fake();
        Storage::fake('local');
        config(['rpz.static_artifacts_enabled' => false, 'rpz.block_page_host' => 'notice.example.net']);
        $server = Servidor::factory()->openAccess()->create();
        $company = $server->empresa;
        $list = Lista::factory()->create();
        $server->listas()->attach($list);
        Dominio::factory()->for($list)->create(['dominio' => 'blocked.example.com']);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('empresas.update', $company), [
            'nome' => $company->nome, 'status' => 'active', 'rpz_bloqueio_modo' => 'redirect',
        ])->assertSessionHasNoErrors();
        $dynamic = $this->get('/rpz/'.$company->rpz_slug.'.zone')->assertOk()->streamedContent();
        $this->assertStringContainsString('blocked.example.com CNAME notice.example.net.', $dynamic);
        $result = app(RpzZoneBuildService::class)->build('company:'.$company->id);
        $this->assertSame('published', $result['status']);
        config(['rpz.static_artifacts_enabled' => true]);
        $published = $this->get('/rpz/'.$company->rpz_slug.'.zone')->assertOk();
        $this->assertStringContainsString('blocked.example.com CNAME notice.example.net.', file_get_contents($published->baseResponse->getFile()->getPathname()));
        $company->fresh()->update(['rpz_bloqueio_modo' => 'nxdomain']);
        app(RpzDirtyMarker::class)->markCompany($company->id);
        app(RpzZoneBuildService::class)->build('company:'.$company->id);
        $published = $this->get('/rpz/'.$company->rpz_slug.'.zone')->assertOk();
        $this->assertStringContainsString('blocked.example.com CNAME .', file_get_contents($published->baseResponse->getFile()->getPathname()));
    }

    public function test_feed_rolls_back_if_dirty_marking_fails_and_does_not_leave_temp_tables(): void
    {
        $list = Lista::factory()->create();
        Dominio::factory()->for($list)->create(['dominio' => 'old.example.com']);
        $marker = \Mockery::mock(RpzDirtyMarker::class);
        $marker->shouldReceive('markForList')->once()->andThrow(new \RuntimeException('simulated failure'));
        $this->app->instance(RpzDirtyMarker::class, $marker);
        try {
            app(SqliteDomainFeedWriter::class)->publish($list, ['new.example.com'], true);
            $this->fail('Expected failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('simulated failure', $error->getMessage());
        }
        $this->assertDatabaseHas('dominios', ['lista_id' => $list->id, 'dominio' => 'old.example.com', 'ativo' => true]);
        $this->assertDatabaseMissing('dominios', ['lista_id' => $list->id, 'dominio' => 'new.example.com']);
        $this->assertNull($list->fresh()->last_sync_at);
        $this->assertSame(0, (int) DB::selectOne("SELECT count(*) AS n FROM sqlite_temp_master WHERE name LIKE 'feed_%'")->n);
    }

    public function test_unchanged_feed_does_not_rewrite_domains_or_schedule_a_new_zone(): void
    {
        Queue::fake();
        $server = Servidor::factory()->create();
        $list = Lista::factory()->create();
        $server->listas()->attach($list);
        $domain = Dominio::factory()->for($list)->create(['dominio' => 'same.example.com', 'updated_at' => now()->subDay()]);
        $before = $domain->updated_at;
        app(SqliteDomainFeedWriter::class)->publish($list, [$domain->dominio], true);
        $this->assertTrue($domain->fresh()->updated_at->equalTo($before));
        $this->assertDatabaseCount('rpz_zones', 0);
        Queue::assertNothingPushed();
    }

    public function test_health_checks_a_configured_public_certificate_and_site(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => 'rpz.example.test'], $key);
        $certificate = openssl_csr_sign($csr, null, $key, 90);
        openssl_x509_export($certificate, $pem);
        $path = tempnam(sys_get_temp_dir(), 'cert-test-');
        file_put_contents($path, $pem);
        config(['health.certificate_path' => $path, 'health.disk_path' => sys_get_temp_dir(), 'health.url' => 'https://rpz.example.test/up']);
        Http::fake(['*' => Http::response('ok')]);
        try {
            $this->artisan('health:check')->assertSuccessful();
            $this->assertDatabaseHas('audit_logs', ['action' => 'health.ok']);
            Http::assertSent(fn ($request) => $request->url() === 'https://rpz.example.test/up');
        } finally {
            unlink($path);
        }
    }

    public function test_ended_lab_uses_standard_refresh_and_company_default_is_nxdomain(): void
    {
        $builder = app(\App\Services\RpzZoneBuilder::class);
        $this->assertSame(3600, $builder->refreshFor('server:15'));
        $company = Empresa::factory()->create()->fresh();
        $this->assertSame('nxdomain', $company->rpz_bloqueio_modo);
        $this->assertSame('.', $builder->companyTarget($company));
    }
}
