<?php

namespace Tests\Unit;

use App\Services\R2BackupUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class R2BackupUploaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_not_configured_by_default(): void
    {
        $uploader = new R2BackupUploader;

        $this->assertFalse($uploader->isConfigured());
    }

    public function test_is_configured_only_when_all_fields_present_and_active(): void
    {
        config([
            'services.r2.account_id' => 'conta',
            'services.r2.access_key_id' => 'chave',
            'services.r2.secret_access_key' => 'secreta',
            'services.r2.bucket' => 'bucket',
        ]);

        \App\Models\Setting::set('r2_ativo', '0');
        $uploader = new R2BackupUploader;
        $this->assertFalse($uploader->isConfigured(), 'inativo nao deveria contar como configurado');

        \App\Models\Setting::set('r2_ativo', '1');
        $this->assertTrue((new R2BackupUploader)->isConfigured());
    }

    public function test_upload_fails_fast_without_hitting_network_when_not_configured(): void
    {
        $uploader = new R2BackupUploader;

        $erro = $uploader->upload('/caminho/inexistente.bak', 'teste.bak');

        $this->assertSame('R2 não está configurado.', $erro);
    }

    public function test_upload_reports_missing_local_file(): void
    {
        config([
            'services.r2.account_id' => 'conta',
            'services.r2.access_key_id' => 'chave',
            'services.r2.secret_access_key' => 'secreta',
            'services.r2.bucket' => 'bucket',
        ]);
        \App\Models\Setting::set('r2_ativo', '1');

        $erro = (new R2BackupUploader)->upload('/caminho/que/nao/existe.bak', 'teste.bak');

        $this->assertSame('Arquivo local não encontrado.', $erro);
    }
}
