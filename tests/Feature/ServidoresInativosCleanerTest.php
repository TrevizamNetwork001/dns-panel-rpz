<?php

namespace Tests\Feature;

use App\Jobs\BuildRpzZone;
use App\Models\AuditLog;
use App\Models\Servidor;
use App\Models\Setting;
use App\Models\User;
use App\Services\ServidoresInativosCleaner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServidoresInativosCleanerTest extends TestCase
{
    use RefreshDatabase;

    private function ativar(int $dias = 60, int $aviso = 7): void
    {
        Setting::set('servidor_limpeza_ativo', '1');
        Setting::set('servidor_limpeza_dias', (string) $dias);
        Setting::set('servidor_limpeza_aviso_dias', (string) $aviso);
    }

    private function servidorParado(int $diasSemConsulta): Servidor
    {
        $servidor = Servidor::factory()->create(['nome' => 'srv-parado']);
        $servidor->forceFill(['last_synced_at' => now()->subDays($diasSemConsulta), 'created_at' => now()->subDays(400)])->saveQuietly();

        return $servidor;
    }

    public function test_does_nothing_when_disabled_by_default(): void
    {
        $servidor = $this->servidorParado(200);

        $resultado = app(ServidoresInativosCleaner::class)->run();

        $this->assertSame('desativada', $resultado['status']);
        $this->assertNotNull(Servidor::find($servidor->id));
        $this->assertNull($servidor->fresh()->inatividade_avisada_em);
    }

    public function test_first_run_only_warns_and_never_deletes(): void
    {
        Http::fake();
        $this->ativar();
        $servidor = $this->servidorParado(120);

        $resultado = app(ServidoresInativosCleaner::class)->run();

        $this->assertCount(1, $resultado['avisados']);
        $this->assertSame([], $resultado['removidos']);
        $this->assertNotNull(Servidor::find($servidor->id));
        $this->assertNotNull($servidor->fresh()->inatividade_avisada_em);
        $this->assertDatabaseHas('audit_logs', ['action' => 'servidor.inatividade_avisada', 'target_id' => $servidor->id]);
    }

    public function test_does_not_touch_recently_active_servers(): void
    {
        $this->ativar();
        $servidor = $this->servidorParado(10);

        $resultado = app(ServidoresInativosCleaner::class)->run();

        $this->assertSame([], $resultado['avisados']);
        $this->assertNull($servidor->fresh()->inatividade_avisada_em);
    }

    public function test_removes_after_warning_period_elapsed(): void
    {
        Http::fake();
        $this->ativar(60, 7);
        $servidor = $this->servidorParado(120);
        $servidor->forceFill(['inatividade_avisada_em' => now()->subDays(8)])->saveQuietly();

        $resultado = app(ServidoresInativosCleaner::class)->run();

        $this->assertCount(1, $resultado['removidos']);
        $this->assertNull(Servidor::find($servidor->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'servidor.removido_inatividade', 'target_id' => $servidor->id]);
    }

    public function test_removing_server_marks_its_rpz_zone_dirty(): void
    {
        // Gap encontrado na auditoria pre-cutover (29/09/2026): a limpeza
        // automatica noturna deletava o servidor sem avisar o versionamento
        // RPZ -- um artefato estatico publicado ficaria preso apontando pra
        // um servidor que nao existe mais.
        Queue::fake();
        Http::fake();
        $this->ativar(60, 7);
        $servidor = $this->servidorParado(120);
        $servidor->forceFill(['inatividade_avisada_em' => now()->subDays(8)])->saveQuietly();

        app(ServidoresInativosCleaner::class)->run();

        Queue::assertPushed(
            BuildRpzZone::class,
            fn ($job) => $job->zoneKey === 'server:'.$servidor->id
        );
    }

    public function test_keeps_server_while_warning_period_is_still_running(): void
    {
        Http::fake();
        $this->ativar(60, 7);
        $servidor = $this->servidorParado(120);
        $servidor->forceFill(['inatividade_avisada_em' => now()->subDays(3)])->saveQuietly();

        $resultado = app(ServidoresInativosCleaner::class)->run();

        $this->assertSame([], $resultado['removidos']);
        $this->assertNotNull(Servidor::find($servidor->id));
    }

    public function test_server_that_came_back_has_warning_cleared(): void
    {
        $this->ativar();
        $servidor = $this->servidorParado(1);
        $servidor->forceFill(['inatividade_avisada_em' => now()->subDays(20)])->saveQuietly();

        $resultado = app(ServidoresInativosCleaner::class)->run();

        $this->assertSame(1, $resultado['recuperados']);
        $this->assertNull($servidor->fresh()->inatividade_avisada_em);
        $this->assertNotNull(Servidor::find($servidor->id));
    }

    public function test_never_synced_server_is_measured_from_creation_date(): void
    {
        Http::fake();
        $this->ativar();
        $velho = Servidor::factory()->create();
        $velho->forceFill(['last_synced_at' => null, 'created_at' => now()->subDays(90)])->saveQuietly();
        $novo = Servidor::factory()->create();

        $resultado = app(ServidoresInativosCleaner::class)->run();

        $this->assertCount(1, $resultado['avisados']);
        $this->assertNotNull($velho->fresh()->inatividade_avisada_em);
        $this->assertNull($novo->fresh()->inatividade_avisada_em);
    }

    public function test_dry_run_reports_but_changes_nothing_even_when_disabled(): void
    {
        $servidor = $this->servidorParado(120);
        $servidor->forceFill(['inatividade_avisada_em' => now()->subDays(30)])->saveQuietly();

        $resultado = app(ServidoresInativosCleaner::class)->run(true);

        $this->assertCount(1, $resultado['removidos']);
        $this->assertNotNull(Servidor::find($servidor->id));
        $this->assertSame(0, AuditLog::where('action', 'like', 'servidor.%')->count());
    }

    public function test_settings_can_be_saved_and_are_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('configuracoes.limpeza-servidores.update'), ['ativo' => '1', 'dias' => 90, 'aviso_dias' => 10])->assertRedirect();
        $config = app(ServidoresInativosCleaner::class)->config();
        $this->assertTrue($config['ativo']);
        $this->assertSame(90, $config['dias']);
        $this->assertSame(10, $config['aviso_dias']);

        $this->put(route('configuracoes.limpeza-servidores.update'), ['dias' => 5, 'aviso_dias' => 10])->assertSessionHasErrors('dias');
    }

    public function test_settings_page_shows_cleanup_section_and_client_cannot_save(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('configuracoes.index'))->assertOk()->assertSee('Limpeza de servidores inativos');

        $cliente = User::factory()->cliente()->create();
        $this->actingAs($cliente)->put(route('configuracoes.limpeza-servidores.update'), ['dias' => 60, 'aviso_dias' => 7])->assertForbidden();
    }
}
