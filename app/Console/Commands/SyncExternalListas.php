<?php

namespace App\Console\Commands;

use App\Models\Lista;
use App\Services\ExternalListaSyncer;
use Illuminate\Console\Command;

class SyncExternalListas extends Command
{
    protected $signature = 'external:sync';

    protected $description = 'Sincroniza todas as listas externas ativas (fonte_url configurável por lista)';

    public function handle(ExternalListaSyncer $syncer): int
    {
        $listas = Lista::where('origem', 'externa')->whereNotNull('fonte_url')->get();

        if ($listas->isEmpty()) {
            $this->info('Nenhuma lista externa configurada.');

            return self::SUCCESS;
        }

        $houveErro = false;

        foreach ($listas as $lista) {
            $resultado = $syncer->syncAndLog($lista);

            match ($resultado['status']) {
                'ok' => $this->info("[{$lista->nome}] OK: {$resultado['total']} ativos, {$resultado['adicionados']} novos, {$resultado['removidos']} desativados."),
                'pausada' => $this->line("[{$lista->nome}] sincronização pausada, pulando."),
                'erro' => (function () use (&$houveErro, $lista, $resultado) {
                    $houveErro = true;
                    $this->error("[{$lista->nome}] ERRO: {$resultado['motivo']}");
                })(),
                default => null,
            };
        }

        return $houveErro ? self::FAILURE : self::SUCCESS;
    }
}
