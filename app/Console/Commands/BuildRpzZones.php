<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\RpzZone;
use App\Models\Servidor;
use App\Services\RpzZoneBuildService;
use Illuminate\Console\Command;

/**
 * Bootstrap/rebuild manual de zonas RPZ estáticas (RPZ-BUILD-1). Não toca
 * Unbound, não faz reload, não chama nada remoto -- só gera/publica o
 * artefato .zone localmente. Sem argumentos, roda dry-run por segurança
 * (precisa de --all ou --zone= combinado com um dos dois pra realmente
 * escrever algo).
 */
class BuildRpzZones extends Command
{
    protected $signature = 'rpz:build
        {--dirty : Reconcilia apenas zonas com geracao pendente}
        {--all : Constrói todas as zonas com pelo menos um servidor/empresa ativo}
        {--zone= : Constrói uma única zona lógica (ex: server:15 ou company:11)}
        {--dry-run : Só lista o que seria feito, sem gravar nada}
        {--no-publish : Constrói e valida em staging, mas não publica como current_version}';

    protected $description = 'Gera/publica o artefato .zone de uma ou todas as zonas RPZ (RPZ-BUILD-1)';

    public function handle(RpzZoneBuildService $service): int
    {
        $zoneKeys = $this->resolveZoneKeys();
        if ($zoneKeys === null) {
            $this->error('Informe --all, --dirty ou --zone=server:<id>|company:<id>.');

            return self::FAILURE;
        }
        if ($zoneKeys === []) {
            $this->info('Nenhuma zona ativa encontrada.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $noPublish = (bool) $this->option('no-publish');

        if ($dryRun) {
            $this->info('Dry-run -- nada será gravado. Zonas que seriam processadas:');
            foreach ($zoneKeys as $zoneKey) {
                $this->line("  {$zoneKey}");
            }

            return self::SUCCESS;
        }

        $falhas = 0;
        foreach ($zoneKeys as $zoneKey) {
            $resultado = $noPublish ? $service->validateOnly($zoneKey) : $service->build($zoneKey);
            $status = $resultado['status'];
            $linha = "{$zoneKey}: {$status}";
            if (isset($resultado['revision'])) {
                $linha .= " (revision {$resultado['revision']}, serial {$resultado['serial']})";
            }

            match ($status) {
                'published', 'restored', 'validated' => $this->info($linha),
                'unchanged', 'noop' => $this->line($linha),
                // skipped_locked/stale_discarded: concorrência normal, nao é erro.
                // skipped_inactive: servidor/empresa sem status ativo ou sem
                // licença ativa -- mesmo gate que o endpoint dinâmico já aplica,
                // não é falha do build.
                'skipped_disabled', 'skipped_locked', 'stale_discarded', 'skipped_inactive' => $this->comment($linha),
                default => (function () use (&$falhas, $linha): void {
                    $falhas++;
                    $this->error($linha);
                })(),
            };
        }

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<string>|null */
    private function resolveZoneKeys(): ?array
    {
        if ($this->option('zone')) {
            return [(string) $this->option('zone')];
        }

        if ($this->option('dirty')) {
            return RpzZone::whereColumn('desired_generation', '>', 'built_generation')->pluck('zone_key')->all();
        }

        if (! $this->option('all')) {
            return null;
        }

        $keys = [];
        foreach (Servidor::where('status', 'active')->pluck('id') as $id) {
            $keys[] = "server:{$id}";
        }
        foreach (Empresa::where('status', 'active')->pluck('id') as $id) {
            $keys[] = "company:{$id}";
        }
        // Zonas que ja existem (ja foram vistas pelo caminho dinamico) mas o
        // servidor/empresa correspondente nao esta mais ativo tambem entram,
        // pra permitir reconciliar built_generation e nao ficar "presa" suja.
        foreach (RpzZone::where('desired_generation', '>', 0)->pluck('zone_key') as $zoneKey) {
            if (! in_array($zoneKey, $keys, true)) {
                $keys[] = $zoneKey;
            }
        }

        return array_values(array_unique($keys));
    }
}
