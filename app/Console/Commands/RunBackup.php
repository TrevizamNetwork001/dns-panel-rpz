<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\R2BackupUploader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class RunBackup extends Command
{
    protected $signature = 'backup:run {--scheduled : Executa somente quando o backup semanal estiver vencido}';

    protected $description = 'Faz o backup local do SQLite (script rpz-backup) e envia pro R2 se configurado';

    public function handle(R2BackupUploader $uploader): int
    {
        $lock = Cache::lock('backup:run', 1800);
        if (! $lock->get()) {
            $this->warn('Já existe um backup em andamento.');

            return self::FAILURE;
        }

        try {
            return $this->runBackup($uploader);
        } finally {
            $lock->release();
        }
    }

    private function runBackup(R2BackupUploader $uploader): int
    {
        if ($this->option('scheduled')) {
            $last = AuditLog::whereIn('action', ['backup.completed', 'backup.r2_uploaded'])->latest('created_at')->first();
            if ($last && $last->created_at->gt(now()->subWeek())) {
                $this->info('Backup semanal ainda não está vencido.');

                return self::SUCCESS;
            }
        }

        $process = new Process(['/usr/local/bin/rpz-backup']);
        $process->setTimeout(600);
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
        $latest = collect(glob("{$backupDir}/database.sqlite.auto-*.bak.gz"))
            ->sort()
            ->last();

        if (! $latest) {
            $this->warn('Backup local rodou, mas nenhum arquivo foi encontrado pra enviar ao R2.');

            return self::FAILURE;
        }

        if (! $uploader->isConfigured()) {
            $this->recordCompletion();
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

        $prune = $uploader->pruneOldBackups();
        if ($prune['removidos'] > 0) {
            AuditLog::create([
                'user_id' => null,
                'empresa_id' => null,
                'action' => 'backup.r2_pruned',
                'description' => "Backups antigos removidos do R2: {$prune['removidos']}",
                'ip_address' => null,
                'created_at' => now(),
            ]);
        }

        if ($prune['erro'] !== null) {
            $this->warn($prune['erro']);
        }
        $usage = $uploader->storageUsage();
        if (($usage['warning'] ?? false) === true) {
            AuditLog::record('backup.storage_warning', 'O bucket R2 atingiu 80% ou mais da referência de 10 GB.');
            $this->warn('O bucket R2 atingiu 80% ou mais da referência de 10 GB.');
        }
        $this->recordCompletion();
        $this->info('Backup local e envio ao R2 concluídos.');

        return self::SUCCESS;
    }

    private function recordCompletion(): void
    {
        AuditLog::create([
            'user_id' => null,
            'empresa_id' => null,
            'action' => 'backup.completed',
            'description' => 'Backup comprimido concluído. Próximo backup automático em uma semana.',
            'ip_address' => null,
            'created_at' => now(),
        ]);
    }
}
