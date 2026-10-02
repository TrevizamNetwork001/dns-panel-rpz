<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\R2BackupUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class R2BackupStorageTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDisk()
    {
        config(['services.r2.account_id' => 'account', 'services.r2.access_key_id' => 'key', 'services.r2.secret_access_key' => 'secret', 'services.r2.bucket' => 'bucket']);
        Setting::set('r2_ativo', '1');
        $disk = Storage::fake('r2-test');
        Storage::shouldReceive('build')->andReturn($disk);

        return $disk;
    }

    public function test_ten_backups_are_reduced_to_five_newest_without_deleting_other_files(): void
    {
        $disk = $this->fakeDisk();
        for ($i = 1; $i <= 10; $i++) {
            $disk->put(sprintf('backups/database.sqlite.auto-202609%02d-120000.bak%s', $i, $i % 2 ? '' : '.gz'), 'backup');
        }
        $disk->put('backups/other.txt', 'keep');
        $result = (new R2BackupUploader)->pruneOldBackups();
        $this->assertSame(5, $result['removidos']);
        $this->assertNull($result['erro']);
        $disk->assertExists('backups/other.txt');
        $disk->assertExists('backups/database.sqlite.auto-20260910-120000.bak.gz');
        $disk->assertMissing('backups/database.sqlite.auto-20260901-120000.bak');
    }

    public function test_age_retention_preserves_five_backups(): void
    {
        $disk = $this->fakeDisk();
        for ($i = 1; $i <= 5; $i++) {
            $path = sprintf('backups/database.sqlite.auto-202601%02d-120000.bak', $i);
            $disk->put($path, 'backup');
            touch($disk->path($path), now()->subDays(90)->timestamp);
        }
        $this->assertSame(0, (new R2BackupUploader)->pruneOldBackups()['removidos']);
    }

    public function test_usage_counts_all_bucket_objects_and_caches_result(): void
    {
        $disk = $this->fakeDisk();
        $disk->put('backups/one.bak', '1234');
        $disk->put('another-folder/file', '123456');
        $uploader = new R2BackupUploader;
        $usage = $uploader->storageUsage();
        $this->assertSame(10, $usage['bytes']);
        $this->assertSame(2, $usage['objects']);
        $this->assertFalse($usage['warning']);
        $this->assertSame(9_999_999_990, $usage['free_tier_remaining_bytes']);
        $this->assertSame($usage, $uploader->cachedUsage());
    }

    public function test_connection_test_leaves_no_temporary_object(): void
    {
        $disk = $this->fakeDisk();
        $disk->put('backups/teste-conexao.txt', 'teste antigo');
        $this->assertNull((new R2BackupUploader)->sendTest());
        $this->assertSame([], $disk->allFiles());
    }

    public function test_eighty_percent_usage_triggers_warning(): void
    {
        config(['services.r2.account_id' => 'account', 'services.r2.access_key_id' => 'key', 'services.r2.secret_access_key' => 'secret', 'services.r2.bucket' => 'bucket']);
        Setting::set('r2_ativo', '1');
        $disk = \Mockery::mock();
        $disk->shouldReceive('listContents')->with('', true)->andReturn([
            new \League\Flysystem\FileAttributes('large.bak', 8_000_000_000),
        ]);
        Storage::shouldReceive('build')->andReturn($disk);
        $usage = (new R2BackupUploader)->storageUsage();
        $this->assertTrue($usage['warning']);
        $this->assertSame(2_000_000_000, $usage['free_tier_remaining_bytes']);
    }

    public function test_scheduled_backup_skips_recent_success(): void
    {
        AuditLog::record('backup.completed', 'ok');
        $this->artisan('backup:run --scheduled')->expectsOutput('Backup semanal ainda não está vencido.')->assertSuccessful();
    }
}
