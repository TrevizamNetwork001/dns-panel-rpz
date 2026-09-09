<?php

namespace App\Console\Commands;

use App\Models\RblRun;
use App\Models\RblTarget;
use App\Services\Rbl\RblChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RblCheckCommand extends Command
{
    protected $signature = 'rbl:check {--target= : ID do alvo ativo} {--limit=100 : Máximo de alvos (1 a 1000)} {--only-enabled : Apenas ativos, comportamento padrão} {--dry-run : Simular sem DNS ou gravação}';

    protected $description = 'Verifica alvos RBL ativos e registra a execução';

    public function handle(RblChecker $checker): int
    {
        foreach (['target', 'limit'] as $option) {
            $value = $this->option($option);
            if ($value !== null && (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || ($option === 'limit' && (int) $value > 1000))) {
                $this->error('Informe target positivo e limit entre 1 e 1000.');

                return self::FAILURE;
            }
        }
        $run = null;
        $lock = null;
        $started = hrtime(true);
        try {
            $query = RblTarget::where('enabled', true);
            if ($this->option('target') !== null) {
                $query->whereKey($this->option('target'));
            }
            // Preserve the manual cooldown and rotate limited batches fairly.
            $query->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subMinute()))
                ->orderByRaw('CASE WHEN last_checked_at IS NULL THEN 0 ELSE 1 END')->orderBy('last_checked_at')->orderBy('id')
                ->limit((int) $this->option('limit'));
            if ($this->option('dry-run')) {
                $this->info('Simulação: '.$query->get(['id'])->count().' alvo(s) elegível(is).');

                return self::SUCCESS;
            }
            $lock = Cache::lock('rbl:scheduled-run', 86400);
            if (! $lock->get()) {
                $this->info('Já existe uma execução RBL em andamento.');

                return self::SUCCESS;
            }
            $run = RblRun::create(['started_at' => now(), 'status' => 'running']);
            foreach ($query->get() as $target) {
                $checker->check($target, $run->id);
                $run->increment('targets_checked');
            }
            $this->finish($run, $started, 'completed');
            $run->refresh();
            $this->info("Execução {$run->id} completed: {$run->targets_checked} alvos; {$run->checks_created} checks; {$run->listed_count} listed; {$run->clean_count} clean; {$run->skipped_count} skipped; {$run->error_count} errors/timeouts.");

            return self::SUCCESS;
        } catch (Throwable) {
            if ($run) {
                try {
                    $this->finish($run, $started, 'failed');
                } catch (Throwable) {
                    // Storage may itself be unavailable; never expose exception details.
                }
            }
            $this->error('Falha estrutural na execução RBL. Verifique banco, cache e listas ativas.');

            return self::FAILURE;
        } finally {
            if ($lock) {
                try {
                    $lock->release();
                } catch (Throwable) {
                    // The lock expires even if the cache becomes unavailable.
                }
            }
        }
    }

    private function finish(RblRun $run, int $started, string $status): void
    {
        $counts = $run->checks()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $run->update([
            'status' => $status, 'finished_at' => now(), 'duration_ms' => (int) ((hrtime(true) - $started) / 1e6),
            'checks_created' => $counts->sum(), 'listed_count' => $counts['listed'] ?? 0,
            'clean_count' => $counts['clean'] ?? 0, 'skipped_count' => $counts['skipped'] ?? 0,
            'error_count' => ($counts['error'] ?? 0) + ($counts['timeout'] ?? 0),
            'error_message' => $status === 'failed' ? 'Falha estrutural na execução RBL.' : null,
        ]);
    }
}
