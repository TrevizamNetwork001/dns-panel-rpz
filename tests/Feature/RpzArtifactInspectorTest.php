<?php

namespace Tests\Feature;

use App\Models\Dominio;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\Servidor;
use App\Services\RpzArtifactInspector;
use App\Services\RpzZoneBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RpzArtifactInspectorTest extends TestCase
{
    use RefreshDatabase;

    private function published(): RpzZone
    {
        Storage::fake('local');
        Queue::fake();
        $server = Servidor::factory()->create();
        $list = Lista::factory()->create(['empresa_id' => $server->empresa_id]);
        $server->listas()->attach($list);
        Dominio::factory()->for($list)->create(['dominio' => 'blocked.example']);
        app(RpzZoneBuildService::class)->build('server:'.$server->id);

        return RpzZone::with('currentVersion')->where('zone_key', 'server:'.$server->id)->firstOrFail();
    }

    public function test_verifies_actual_published_bytes_without_writing_or_dispatching(): void
    {
        $zone = $this->published();
        $before = $zone->getAttributes();
        $version = $zone->currentVersion->getAttributes();
        $files = Storage::disk('local')->allFiles();
        $auditCount = \App\Models\AuditLog::count();
        Queue::fake();
        $report = app(RpzArtifactInspector::class)->inspect($zone);
        $this->assertTrue($report['ok'], implode(';', $report['errors']));
        $this->assertSame(2, $report['actions']['nxdomain']);
        $this->artisan('rpz:verify', ['zone_key' => $zone->zone_key])->expectsOutputToContain('OK')->assertSuccessful();
        $this->assertSame($before, $zone->fresh()->getAttributes());
        $this->assertSame($version, $zone->currentVersion->fresh()->getAttributes());
        $this->assertSame($auditCount, \App\Models\AuditLog::count());
        $this->assertSame($files, Storage::disk('local')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_missing_and_corrupted_files_fail_without_repair(): void
    {
        $zone = $this->published();
        $v = $zone->currentVersion;
        Storage::disk('local')->put($v->file_path, 'corrupt');
        $this->artisan('rpz:verify', ['zone_key' => $zone->zone_key])->expectsOutputToContain('SHA-256 diferente')->assertFailed();
        $this->assertSame('corrupt', Storage::disk('local')->get($v->file_path));
        Storage::disk('local')->delete($v->file_path);
        $this->artisan('rpz:verify', ['zone_key' => $zone->zone_key])->expectsOutputToContain('Arquivo publicado ausente')->assertFailed();
        $this->assertFalse(Storage::disk('local')->exists($v->file_path));
    }

    public function test_hash_alone_does_not_hide_missing_canary_or_oversized_owner(): void
    {
        $zone = $this->published();
        $v = $zone->currentVersion;
        $owner = implode('.', array_fill(0, 4, str_repeat('a', 60)));
        $content = $owner." CNAME .\nallowed.example CNAME rpz-passthru.\nredirect.example CNAME sink.example.\n";
        Storage::disk('local')->put($v->file_path, $content);
        $v->update(['file_hash' => hash('sha256', $content), 'file_size' => strlen($content), 'rule_count' => 2]);
        $report = app(RpzArtifactInspector::class)->inspect($zone);
        $this->assertFalse($report['ok']);
        $this->assertContains('Domínio de teste sem bloqueio NXDOMAIN.', $report['errors']);
        $this->assertContains('1 regras excedem o limite de nomes DNS.', $report['errors']);
        $this->assertSame(['nxdomain' => 1, 'passthru' => 1, 'redirect' => 1], $report['actions']);
    }

    public function test_rejects_cross_zone_version_and_unexpected_path(): void
    {
        $zone = $this->published();
        $zone->id += 1000;
        $this->assertContains('Referência de versão publicada inválida.', app(RpzArtifactInspector::class)->inspect($zone)['errors']);
        $zone->id -= 1000;
        $zone->currentVersion->file_path = '../unexpected.zone';
        $this->assertContains('Caminho do artefato incompatível com a versão.', app(RpzArtifactInspector::class)->inspect($zone)['errors']);
    }

    public function test_reports_no_version_unknown_zone_and_invalid_key(): void
    {
        $zone = new RpzZone(['zone_key' => 'server:1']);
        $this->assertContains('Sem versão publicada.', app(RpzArtifactInspector::class)->inspect($zone)['errors']);
        $this->artisan('rpz:verify', ['zone_key' => 'server:999'])->assertFailed();
        $this->artisan('rpz:verify', ['zone_key' => '../invalid'])->assertExitCode(2);
    }
}
