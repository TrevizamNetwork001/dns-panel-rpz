<?php

namespace App\Console\Commands;

use App\Services\ServidoresInativosCleaner;
use Illuminate\Console\Command;

class LimparServidoresInativos extends Command
{
    protected $signature = 'servidores:limpar-inativos {--dry-run : Mostra o que seria avisado/removido sem alterar nada}';

    protected $description = 'Avisa e, passado o prazo de aviso, remove servidores sem consultar a zona RPZ além do limite configurado';

    public function handle(ServidoresInativosCleaner $cleaner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $resultado = $cleaner->run($dryRun);

        if ($resultado['status'] === 'desativada') {
            $this->info('Limpeza de servidores inativos está desativada em Configurações.');

            return self::SUCCESS;
        }

        $prefixo = $dryRun ? '[simulação] ' : '';
        foreach ($resultado['avisados'] as $item) {
            $this->line("{$prefixo}AVISO: {$item['empresa']} / {$item['servidor']} — {$item['dias']} dia(s) sem consulta");
        }
        foreach ($resultado['removidos'] as $item) {
            $this->warn("{$prefixo}REMOVER: {$item['empresa']} / {$item['servidor']} — {$item['dias']} dia(s) sem consulta");
        }
        $this->info("{$prefixo}Avisados: ".count($resultado['avisados']).' · Removidos: '.count($resultado['removidos']).' · Voltaram a consultar: '.$resultado['recuperados']);

        return self::SUCCESS;
    }
}
