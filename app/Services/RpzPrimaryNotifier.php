<?php

namespace App\Services;

use App\Models\RpzZone;
use Illuminate\Support\Facades\Log;

/**
 * Envia DNS NOTIFY (RFC 1996) pros servidores de cliente assim que uma nova
 * versao e publicada -- permite manter o refresh do SOA longo (ex: 24h,
 * baixo overhead de sondagem) sem perder velocidade de reacao: o Unbound do
 * cliente, ao receber o NOTIFY, faz o probe na hora, sem esperar o proximo
 * ciclo agendado. "Melhor esforco": falha de notify nunca derruba o build
 * nem o publish (o proximo ciclo natural do refresh continua como rede de
 * seguranca).
 *
 * So server:<id> nesta primeira versao (mesma limitacao do
 * RpzPrimaryResponder) -- o IP de destino e o mesmo ja cadastrado em
 * Servidor::allowedIps, reaproveitado do controle de acesso do endpoint
 * HTTP.
 */
class RpzPrimaryNotifier
{
    private const NOTIFY_PORT = 53;

    private const OPCODE_NOTIFY = 4;

    public function notify(RpzZone $zone): void
    {
        if (! config('rpz.primary_enabled')) {
            return;
        }

        [$scopeType, $scopeId] = array_pad(explode(':', $zone->zone_key, 2), 2, null);
        if ($scopeType !== 'server' || $scopeId === null) {
            return;
        }

        $servidor = \App\Models\Servidor::with(['allowedIps' => fn ($q) => $q->where('status', 'active')])
            ->find((int) $scopeId);
        if ($servidor === null) {
            return;
        }

        $zoneName = app(RpzZoneBuilder::class)->panelHost();

        foreach ($servidor->allowedIps as $regra) {
            $ip = trim((string) $regra->ip_cidr);
            // NOTIFY so faz sentido pra um IP unico (a maquina real do
            // cliente) -- ignora entradas em formato de bloco/CIDR, essas
            // continuam cobertas pelo refresh natural do SOA.
            if ($ip === '' || str_contains($ip, '/')) {
                continue;
            }

            $this->sendNotify($ip, $zoneName);
        }
    }

    private function sendNotify(string $ip, string $zoneName): void
    {
        $socket = @socket_create(str_contains($ip, ':') ? AF_INET6 : AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            return;
        }

        try {
            $packet = $this->buildNotifyPacket($zoneName);
            @socket_sendto($socket, $packet, strlen($packet), 0, $ip, self::NOTIFY_PORT);
        } catch (\Throwable $e) {
            Log::channel(config('logging.default'))->debug("rpz primary notify falhou pra {$ip}: {$e->getMessage()}");
        } finally {
            socket_close($socket);
        }
    }

    private function buildNotifyPacket(string $zoneName): string
    {
        $id = random_int(0, 0xFFFF);
        // QR=0, Opcode=4 (NOTIFY), AA=1, resto 0 -> 0010 0100 0000 0000.
        $flags = (self::OPCODE_NOTIFY << 11) | 0x0400;

        $header = pack('nnnnnn', $id, $flags, 1, 0, 0, 0);
        $question = $this->encodeName($zoneName).pack('nn', 6, 1); // QTYPE=SOA, QCLASS=IN

        return $header.$question;
    }

    private function encodeName(string $domain): string
    {
        $domain = rtrim($domain, '.');
        if ($domain === '') {
            return "\x00";
        }

        $encoded = '';
        foreach (explode('.', $domain) as $label) {
            $encoded .= chr(strlen($label)).$label;
        }

        return $encoded."\x00";
    }
}
