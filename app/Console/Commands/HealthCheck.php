<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class HealthCheck extends Command
{
    protected $signature = 'health:check';

    protected $description = 'Verifica disco, validade do certificado TLS e se o site responde; registra anomalias na auditoria';

    private const DISK_WARNING_PERCENT = 85;

    private const CERT_WARNING_DAYS = 14;

    private const CERT_PATH = '/etc/letsencrypt/live/rpz.trevizamnetwork.com.br/cert.pem';

    private const HEALTH_URL = 'https://rpz.trevizamnetwork.com.br/up';

    public function handle(): int
    {
        $problemas = [];

        $problemas = array_merge($problemas, $this->checkDisk());
        $problemas = array_merge($problemas, $this->checkCert());
        $problemas = array_merge($problemas, $this->checkSiteUp());

        if (empty($problemas)) {
            AuditLog::create([
                'user_id' => null,
                'empresa_id' => null,
                'action' => 'health.ok',
                'description' => 'Healthcheck: disco, certificado e site OK',
                'ip_address' => null,
                'created_at' => now(),
            ]);
            $this->info('Tudo OK: disco, certificado e site.');

            return self::SUCCESS;
        }

        foreach ($problemas as $problema) {
            AuditLog::create([
                'user_id' => null,
                'empresa_id' => null,
                'action' => $problema['action'],
                'description' => $problema['description'],
                'ip_address' => null,
                'created_at' => now(),
            ]);
            $this->warn($problema['description']);
        }

        return self::FAILURE;
    }

    private function checkDisk(): array
    {
        $total = @disk_total_space('/');
        $free = @disk_free_space('/');

        if (! $total || ! $free) {
            return [];
        }

        $usedPercent = round((1 - $free / $total) * 100, 1);

        if ($usedPercent >= self::DISK_WARNING_PERCENT) {
            return [[
                'action' => 'health.disk_low',
                'description' => "Disco do servidor em {$usedPercent}% de uso (limite de alerta: " . self::DISK_WARNING_PERCENT . '%)',
            ]];
        }

        return [];
    }

    private function checkCert(): array
    {
        if (! is_readable(self::CERT_PATH)) {
            return [[
                'action' => 'health.cert_unreadable',
                'description' => 'Não foi possível ler o certificado TLS para checar a validade (' . self::CERT_PATH . ')',
            ]];
        }

        $cert = @openssl_x509_parse(file_get_contents(self::CERT_PATH));

        if (! $cert || empty($cert['validTo_time_t'])) {
            return [[
                'action' => 'health.cert_unreadable',
                'description' => 'Não foi possível interpretar o certificado TLS em ' . self::CERT_PATH,
            ]];
        }

        $diasRestantes = (int) floor(($cert['validTo_time_t'] - time()) / 86400);

        if ($diasRestantes <= self::CERT_WARNING_DAYS) {
            return [[
                'action' => 'health.cert_expiring',
                'description' => "Certificado TLS expira em {$diasRestantes} dia(s) — renovação automática do Certbot deveria cuidar disso, mas confira",
            ]];
        }

        return [];
    }

    private function checkSiteUp(): array
    {
        try {
            $response = Http::timeout(10)->get(self::HEALTH_URL);
        } catch (\Throwable $e) {
            return [[
                'action' => 'health.site_down',
                'description' => 'Site não respondeu em ' . self::HEALTH_URL . ': ' . $e->getMessage(),
            ]];
        }

        if (! $response->successful()) {
            return [[
                'action' => 'health.site_down',
                'description' => 'Site respondeu com status ' . $response->status() . ' em ' . self::HEALTH_URL,
            ]];
        }

        return [];
    }
}
