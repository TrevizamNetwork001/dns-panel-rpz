<?php

namespace App\Jobs;

use App\Services\RpzZoneBuildService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Acorda um worker pra (re)construir o artefato .zone de uma zona lógica.
 *
 * Deliberadamente NÃO usa ShouldBeUnique: a correção deste sistema não
 * depende da fila ter no máximo 1 job por zona. desired_generation/
 * built_generation (persistidos no banco) são a fonte durável de verdade;
 * flock() por zona garante exclusão real do build. Jobs duplicados na fila
 * são aceitáveis e baratos -- RpzZoneBuildService vira no-op se a zona já
 * estiver reconciliada (built_generation >= desired_generation) ou se outro
 * worker já estiver com o lock.
 */
class BuildRpzZone implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public string $zoneKey) {}

    public function handle(RpzZoneBuildService $service): void
    {
        $service->build($this->zoneKey);
    }
}
