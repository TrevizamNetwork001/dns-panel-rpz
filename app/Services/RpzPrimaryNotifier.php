<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\RpzZone;
use App\Models\Servidor;
use Illuminate\Support\Facades\Log;

/** NOTIFY is enabled independently of the SOA listener and restricted to explicit server IDs. */
class RpzPrimaryNotifier
{
    public function __construct(private RpzNotifyTransport $transport) {}

    public function notify(RpzZone $zone): void
    {
        if (! config('rpz.endpoints_enabled', true) || ! config('rpz.publication_enabled', true)
            || ! config('rpz.notify_enabled') || ! config('rpz.static_artifacts_enabled')) {
            return;
        }
        [$scope, $id] = array_pad(explode(':', $zone->zone_key, 2), 2, null);
        $pilotIds = array_map('strval', config('rpz.notify_server_ids', []));
        $companyIds = array_map('strval', config('rpz.primary_company_server_ids', []));
        $version = $zone->currentVersion;
        if ($version === null || $version->status !== 'published' || $version->rpz_zone_id !== $zone->id) {
            return;
        }
        $servers = Servidor::with(['allowedIps' => fn ($q) => $q->where('status', 'active'), 'empresa'])
            ->where('status', 'active')->whereIn('id', $pilotIds);
        if ($scope === 'server' && ! in_array((string) $id, $companyIds, true)) {
            $servers->where('id', (int) $id);
        } elseif ($scope === 'company') {
            $servers->where('empresa_id', (int) $id)->whereIn('id', $companyIds);
        } else {
            return;
        }
        foreach ($servers->get() as $server) {
            if ($server->empresa?->status === 'active' && $server->empresa->possuiLicencaAtiva()) {
                $this->notifyServer($zone, $server);
            }
        }
    }

    private function notifyServer(RpzZone $zone, Servidor $server): void
    {
        $version = $zone->currentVersion;
        $destinations = [];
        foreach ($server->allowedIps as $rule) {
            $ip = $this->hostAddress(trim((string) $rule->ip_cidr));
            if ($ip !== null && (config('rpz.notify_ipv6_enabled', true) || ! str_contains($ip, ':'))) {
                $destinations[$ip] = true;
            }
        }
        if ($destinations === []) {
            AuditLog::record('rpz.notify.skipped', "NOTIFY {$zone->zone_key}: nenhum IP individual habilitado", $server->empresa_id, 'servidor', $server->id);
        }
        foreach (array_keys($destinations) as $ip) {
            try {
                $result = $this->transport->send($ip, $zone->zone_name);
            } catch (\Throwable $e) {
                $result = ['status' => 'failed', 'attempts' => 0, 'error' => $e->getMessage()];
            }
            $description = "NOTIFY {$zone->zone_key} serial {$version->serial} para {$ip}: {$result['status']} ({$result['attempts']} tentativa(s))";
            if ($result['error'] !== null) {
                $description .= '; '.$result['error'];
            }
            // Persist independently of LOG_LEVEL; distinguish socket success from an ACK.
            AuditLog::record('rpz.notify.'.$result['status'], $description, $server->empresa_id, 'servidor', $server->id);
            if (in_array($result['status'], ['failed', 'rejected'], true)) {
                Log::error($description);
            }
        }
    }

    private function hostAddress(string $value): ?string
    {
        [$ip, $prefix] = array_pad(explode('/', $value, 2), 2, null);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $bits = str_contains($ip, ':') ? 128 : 32;
        if ($prefix !== null && $prefix !== (string) $bits) {
            return null;
        }

        return inet_ntop(inet_pton($ip));
    }
}
