<?php

namespace App\Services;

use App\Jobs\BuildRpzZone;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\Servidor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ponto único de decisão de "quais zonas lógicas uma mutação afeta" (RPZ-
 * BUILD-1). Controllers/services que alteram conteúdo da RPZ chamam este
 * serviço em vez de calcular zone_key na mão -- evita cada lugar reinventar
 * a regra e divergir.
 *
 * desired_generation é a fonte durável de verdade de "precisa rebuild"
 * (persistida no banco, sobrevive a reinício/falha de fila). O dispatch do
 * job é só um mecanismo de acordar um worker mais cedo -- nunca a garantia
 * de correção. Ver App\Jobs\BuildRpzZone e App\Services\RpzZoneBuildService.
 */
class RpzDirtyMarker
{
    /** Marca a zona do servidor (server:<id>) como precisando de rebuild. */
    public function markServer(int $servidorId): void
    {
        $this->bump('server:'.$servidorId);
    }

    /** Marca a zona agregada da empresa (company:<id>) como precisando de rebuild. */
    public function markCompany(int $empresaId): void
    {
        $this->bump('company:'.$empresaId);
    }

    /**
     * Uma lista mudou (conteúdo, status, ou vínculo) -- marca todo servidor
     * atualmente vinculado a ela e a empresa de cada um (a zona empresarial
     * agrega todos os servidores ativos da empresa, então também é afetada).
     */
    public function markForList(int $listaId): void
    {
        $lista = Lista::with('servidores:id,empresa_id')->find($listaId);
        if ($lista === null) {
            return;
        }
        $this->markServersAndTheirCompanies($lista->servidores);
    }

    /**
     * Marca um conjunto arbitrário de servidores (e as empresas deles) --
     * útil quando um vínculo lista↔servidor muda e é preciso cobrir tanto
     * quem perdeu quanto quem ganhou o vínculo (sync() só mostra o depois).
     *
     * @param  iterable<int>  $servidorIds
     */
    public function markForServers(iterable $servidorIds): void
    {
        $ids = array_unique(array_map('intval', is_array($servidorIds) ? $servidorIds : iterator_to_array($servidorIds)));
        if ($ids === []) {
            return;
        }
        $this->markServersAndTheirCompanies(Servidor::whereIn('id', $ids)->get(['id', 'empresa_id']));
    }

    /**
     * Um servidor mudou de forma que afeta só a zona dele (bloqueio_modo,
     * status, vínculos de lista) -- não precisa marcar a empresa, já que o
     * conjunto de domínios agregado da empresa não muda (só como aqueles
     * domínios são servidos por ESTE servidor específico).
     */
    public function markForServer(int $servidorId): void
    {
        $this->markServer($servidorId);
    }

    /**
     * A empresa mudou de forma que afeta autorização/emissão (status,
     * ativação/desativação) -- isso invalida a zona empresarial E, por
     * cascata, a zona de cada servidor dela (o ACL/licença da empresa
     * também é checado no endpoint por token de cada servidor).
     */
    public function markForCompany(int $empresaId): void
    {
        $this->markCompany($empresaId);
        $servidorIds = Servidor::where('empresa_id', $empresaId)->pluck('id');
        foreach ($servidorIds as $servidorId) {
            $this->markServer($servidorId);
        }
    }

    /**
     * @param  Collection<int, Servidor>  $servidores
     */
    private function markServersAndTheirCompanies(Collection $servidores): void
    {
        $empresaIds = [];
        foreach ($servidores as $servidor) {
            $this->markServer($servidor->id);
            if ($servidor->empresa_id !== null) {
                $empresaIds[$servidor->empresa_id] = true;
            }
        }
        foreach (array_keys($empresaIds) as $empresaId) {
            $this->markCompany((int) $empresaId);
        }
    }

    /**
     * Incrementa desired_generation atomicamente (UPDATE ... SET generation =
     * generation + 1, sem race de leitura-then-escrita) e despacha um job de
     * build. Se a rpz_zone ainda não existe (zona nunca foi vista), não há o
     * que incrementar -- o job de build cria a linha no bootstrap.
     */
    private function bump(string $zoneKey): void
    {
        $zone = RpzZone::where('zone_key', $zoneKey)->first();
        if ($zone !== null) {
            DB::table('rpz_zones')->where('id', $zone->id)->increment('desired_generation');
        }

        BuildRpzZone::dispatch($zoneKey);
    }
}
