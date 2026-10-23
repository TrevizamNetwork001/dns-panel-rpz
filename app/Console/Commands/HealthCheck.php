<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\TelegramNotifier;
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

    public function handle(TelegramNotifier $telegram): int
    {
        $problemas = [];

        $problemas = array_merge($problemas, $this->checkDisk());
        $problemas = array_merge($problemas, $this->checkCert());
        $problemas = array_merge($problemas, $this->checkSiteUp());

        if (empty($problemas)) {
            $ultimaAcao = AuditLog::where('action', 'like', 'health.%')
                ->orderByDesc('id')
                ->value('action');

            AuditLog::create([
                'user_id' => null,
                'empresa_id' => null,
                'action' => 'health.ok',
                'description' => 'Healthcheck: disco, certificado e site OK',
                'ip_address' => null,
                'created_at' => now(),
            ]);
            $this->info('Tudo OK: disco, certificado e site.');

            // Só avisa recuperação se o healthcheck anterior tinha detectado problema;
            // silencioso nas execuções normais pra não gerar ruído a cada 30min.
            if ($ultimaAcao !== null && $ultimaAcao !== 'health.ok') {
                $telegram->notifyHealthRecovered();
            }

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

        // A indisponibilidade do Telegram nunca mascara o resultado real do
        // healthcheck: o notifier trata falhas, registra no log e retorna false.
        $telegram->notifyHealthProblems($problemas);

        return self::FAILURE;
    }

    private function checkDisk(): array
    {
        $path = config('health.disk_path', '/');
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $total <= 0 || $free === false) {
            return [['action' => 'health.disk_unreadable', 'description' => 'Não foi possível consultar o espaço em disco.']];
        }

        $usedPercent = round((1 - $free / $total) * 100, 1);

        if ($usedPercent >= self::DISK_WARNING_PERCENT) {
            return [[
                'action' => 'health.disk_low',
                'description' => "Disco do servidor em {$usedPercent}% de uso (limite de alerta: ".self::DISK_WARNING_PERCENT.'%)',
            ]];
        }

        return [];
    }

    private function checkCert(): array
    {
        $path = config('health.certificate_path', self::CERT_PATH);
        if (! is_readable($path)) {
            return [[
                'action' => 'health.cert_unreadable',
                'description' => 'Não foi possível ler o certificado TLS para checar a validade.',
            ]];
        }

        $cert = @openssl_x509_parse(file_get_contents($path));

        if (! $cert || empty($cert['validTo_time_t'])) {
            return [[
                'action' => 'health.cert_unreadable',
                'description' => 'Não foi possível interpretar o certificado TLS.',
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
        $url = config('health.url', self::HEALTH_URL);
        try {
            $response = Http::connectTimeout(5)->timeout(10)->get($url);
        } catch (\Throwable $e) {
            return [[
                'action' => 'health.site_down',
                'description' => 'O endereço de verificação do painel não respondeu.',
            ]];
        }

        if (! $response->successful()) {
            return [[
                'action' => 'health.site_down',
                'description' => 'O painel respondeu à verificação com status '.$response->status().'.',
            ]];
        }

        return [];
    }
}
