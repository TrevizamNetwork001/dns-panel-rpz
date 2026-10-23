<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class RunDatabaseBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 660;
    public int $tries = 1;

    public function handle(): void
    {
        if (Artisan::call('backup:run') !== 0) {
            throw new RuntimeException('Backup não concluído. Consulte os eventos de backup na auditoria.');
        }
    }
}
