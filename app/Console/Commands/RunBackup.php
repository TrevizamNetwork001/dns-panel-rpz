<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\R2BackupUploader;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Faz o backup local do SQLite (script rpz-backup) e envia pro R2 se configurado';

    public function handle(R2BackupUploader $uploader): int
    {
        $process = new Process(['/usr/local/bin/rpz-backup']);
        $process->setTimeout(120);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            AuditLog::create([
                'user_id' => null,
                'empresa_id' => null,
                'action' => 'backup.failed',
                'description' => 'Backup local do SQLite falhou.',
                'ip_address' => null,
                'created_at' => now(),
            ]);

            return self::FAILURE;
        }

        $backupDir = rtrim(env('BACKUP_DIR', '/backups'), '/');
        $latest = collect(glob("{$backupDir}/database.sqlite.auto-*.bak"))
            ->sort()
            ->last();

        if (! $latest) {
            $this->warn('Backup local rodou, mas nenhum arquivo foi encontrado pra enviar ao R2.');

            return self::SUCCESS;
        }

        if (! $uploader->isConfigured()) {
            $this->info('Backup local concluído. R2 não configurado — pulando envio externo.');

            return self::SUCCESS;
        }

        $erro = $uploader->upload($latest, basename($latest));

        AuditLog::create([
            'user_id' => null,
            'empresa_id' => null,
            'action' => $erro === null ? 'backup.r2_uploaded' : 'backup.r2_failed',
            'description' => $erro === null
                ? 'Backup enviado ao R2: '.basename($latest)
                : 'Falha ao enviar backup ao R2: '.$erro,
            'ip_address' => null,
            'created_at' => now(),
        ]);

        if ($erro !== null) {
            $this->error($erro);

            return self::FAILURE;
        }

        $this->info('Backup local e envio ao R2 concluídos.');

        return self::SUCCESS;
    }
}
