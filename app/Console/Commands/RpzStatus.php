<?php

namespace App\Console\Commands;

use App\Models\RpzZone;
use Illuminate\Console\Command;

/**
 * Relatório read-only do estado de versionamento RPZ (observabilidade do
 * cutover, RPZ-CUTOVER-1). Não escreve nada, não builda, não toca Unbound.
 */
class RpzStatus extends Command
{
    protected $signature = 'rpz:status {--dirty-only : Mostra só zonas com desired_generation > built_generation}';

    protected $description = 'Relatório read-only do estado de cada zona RPZ (revision, serial, artefato, dirty)';

    public function handle(): int
    {
        $query = RpzZone::with('currentVersion')->orderBy('zone_key');
        if ($this->option('dirty-only')) {
            $query->whereColumn('desired_generation', '>', 'built_generation');
        }
        $zonas = $query->get();

        if ($zonas->isEmpty()) {
            $this->info('Nenhuma zona registrada ainda (rode rpz:build pra criar a primeira versão).');

            return self::SUCCESS;
        }

        $linhas = $zonas->map(function (RpzZone $zona) {
            $v = $zona->currentVersion;
            $dirty = $zona->desired_generation > $zona->built_generation;

            return [
                $zona->zone_key,
                $v?->revision ?? '-',
                $v?->serial ?? '-',
                $v?->published_at?->format('d/m/Y H:i:s') ?? 'nunca',
                $v?->domain_count ?? '-',
                $v?->rule_count ?? '-',
                $v?->file_size !== null ? number_format($v->file_size / 1048576, 2).'MB' : '-',
                $zona->desired_generation,
                $zona->built_generation,
                $dirty ? 'SIM' : 'não',
                $v?->status ?? 'sem versão',
            ];
        });

        $this->table(
            ['zone_key', 'revision', 'serial', 'published_at', 'domains', 'rules', 'tamanho', 'desired_gen', 'built_gen', 'dirty', 'último status'],
            $linhas,
        );

        $sujas = $zonas->filter(fn (RpzZone $z) => $z->desired_generation > $z->built_generation)->count();
        $semVersao = $zonas->filter(fn (RpzZone $z) => $z->current_version_id === null)->count();
        $this->newLine();
        $this->line("Total: {$zonas->count()} zonas. Dirty: {$sujas}. Sem versão publicada: {$semVersao}. Flag estática: ".(config('rpz.static_artifacts_enabled') ? 'ATIVA' : 'desligada').'.');

        return self::SUCCESS;
    }
}
