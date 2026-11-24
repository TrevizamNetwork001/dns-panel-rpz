<?php

namespace App\Services;

use App\Models\RpzZone;
use App\Models\Servidor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RpzSynchronizationStatus
{
    public function company(int $company): array
    {
        $zone = RpzZone::with('currentVersion')->where('zone_key', 'company:'.$company)->first();
        $enabled = config('rpz.endpoints_enabled') && config('rpz.publication_enabled');

        return $this->publication($zone) + ['publicacao_habilitada' => $enabled];
    }

    public function servers(int $company, int $perPage): LengthAwarePaginator
    {
        $servers = Servidor::where('empresa_id', $company)->orderBy('id')->paginate($perPage);
        $companyKey = 'company:'.$company;
        $keys = $servers->getCollection()->map(fn (Servidor $server) => 'server:'.$server->id)->push($companyKey);
        $zones = RpzZone::with('currentVersion')->whereIn('zone_key', $keys)->get()->keyBy('zone_key');

        $reports = DB::table('tenant_rpz_reports')->where('empresa_id', $company)
            ->whereIn('servidor_id', $servers->getCollection()->modelKeys())->get()->keyBy('servidor_id');

        return $servers->through(function (Servidor $server) use ($companyKey, $zones, $reports) {
            $report = $reports->get($server->id);
            $serverKey = 'server:'.$server->id;
            $log = $server->syncLogs()->whereIn('zone_key', [$companyKey, $serverKey])
                ->latest('created_at')->latest('id')->first();

            return [
                'id' => $server->id,
                'nome' => $server->nome,
                'status' => $server->status,
                'publicacao_individual' => $this->publication($zones->get($serverKey)),
                'ultimo_acesso_feed_em' => $server->last_synced_at?->toISOString(),
                'ultima_confirmacao' => $report ? [
                    'em' => Carbon::parse($report->reported_at)->toISOString(),
                    'serial' => (int) $report->serial,
                    'zona' => $report->zone_scope,
                    'publicacao_da_zona' => $this->publication($zones->get($report->zone_scope === 'empresa' ? $companyKey : $serverKey)),
                ] : null,
                'ultima_entrega_rpz' => $log ? [
                    'em' => $log->created_at?->toISOString(),
                    'zona' => $log->zone_key === $companyKey ? 'empresa' : 'servidor',
                    'revisao' => $log->revision === null ? null : (int) $log->revision,
                    'publicacao_da_zona' => $this->publication($zones->get($log->zone_key)),
                ] : null,
            ];
        });
    }

    private function publication(?RpzZone $zone): array
    {
        $version = $zone?->currentVersion;

        return [
            'status' => match (true) {
                $zone?->needsRebuild() === true => 'pendente',
                $version !== null => 'publicada',
                default => 'sem_publicacao',
            },
            'revisao' => $version?->revision,
            'serial' => $version?->serial,
            'publicada_em' => $version?->published_at?->toISOString(),
        ];
    }
}
