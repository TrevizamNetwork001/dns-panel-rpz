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

    /** Status e vinculos de um servidor tambem afetam a zona agregada da empresa. */
    public function markForServer(int $servidorId): void
    {
        $this->markForServers([$servidorId]);
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

    /** Persist before dispatch, including the very first build, so reconciliation can recover lost jobs. */
    private function bump(string $zoneKey): void
    {
        [$scope, $id] = explode(':', $zoneKey, 2);
        $entity = $scope === 'server' ? Servidor::find((int) $id) : Empresa::find((int) $id);
        $zone = RpzZone::where('zone_key', $zoneKey)->first();
        if ($entity === null && $zone === null) {
            BuildRpzZone::dispatch($zoneKey)->afterCommit();

            return;
        }
        if ($zone === null) {
            $zone = RpzZone::firstOrCreate(['zone_key' => $zoneKey], [
                'scope_type' => $scope,
                'empresa_id' => $scope === 'company' ? (int) $id : $entity->empresa_id,
                'servidor_id' => $scope === 'server' ? (int) $id : null,
                'zone_name' => app(RpzZoneBuilder::class)->panelHost(),
                'last_serial' => 0,
                'revision' => 0,
                'content_hash' => '',
            ]);
        }
        DB::table('rpz_zones')->where('id', $zone->id)->increment('desired_generation');
        BuildRpzZone::dispatch($zoneKey)->afterCommit();
    }
}
