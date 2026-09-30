<?php

namespace App\Services;

use App\Models\RpzZone;
use App\Models\Servidor;

/**
 * "Primary" nativo do RPZ (RPZ-UNBOUND-LAB-1, fase de producao real,
 * 29/09/2026): responde sondagens SOA-only (o "bilhetinho" -- ver
 * RpzZoneBuilder::refreshFor()) pra que o Unbound do cliente decida sozinho
 * se precisa baixar a zona de novo via `url:`, sem nunca fazer download
 * completo so pra checar. Protocolo DNS puro (UDP), nao HTTP -- por isso
 * fica num servico dedicado (RpzPrimaryServe), nao numa rota do painel.
 *
 * So responde SOA pra IPs ja cadastrados/liberados (mesma regra de acesso
 * do endpoint HTTP, Servidor::ipAllowed()) -- nunca vira um refletor aberto
 * pra IP desconhecido (risco classico de amplificacao DNS).
 */
class RpzPrimaryResponder
{
    private const TYPE_SOA = 6;

    private const CLASS_IN = 1;

    public function __construct(private RpzZoneBuilder $builder) {}

    /**
     * Processa um pacote de consulta DNS bruto e devolve a resposta pronta
     * pra ser enviada de volta (ou null se a consulta for invalida, o IP nao
     * estiver autorizado, ou a zona pedida nao tiver nenhuma versao ainda --
     * em todos esses casos o pacote e simplesmente descartado, sem resposta).
     */
    public function respond(string $packet, string $sourceIp): ?string
    {
        $query = $this->parseQuery($packet);
        if ($query === null) {
            return null;
        }

        $zone = $this->resolveZoneForIp($sourceIp);
        if ($zone === null || $zone->current_version_id === null) {
            return null;
        }

        $refresh = $this->builder->refreshFor($zone->zone_key);
        $host = $this->builder->panelHost();

        return $this->buildSoaResponse(
            id: $query['id'],
            qname: $query['qname'],
            qtype: $query['qtype'],
            qclass: $query['qclass'],
            serial: $zone->last_serial,
            refresh: $refresh,
            retry: 600,
            expire: 86400,
            minimum: RpzZoneBuilder::TTL,
            ttl: RpzZoneBuilder::TTL,
            mname: $host.'.',
            rname: 'hostmaster.'.$host.'.',
        );
    }

    /**
     * O nome da zona perguntado (qname) e sempre o mesmo texto literal pra
     * todo mundo (ex: rpz.trevizamnetwork.com.br) -- quem diferencia qual
     * zona logica (server:<id> ou company:<id>) e o IP de origem. So cobre
     * server:<id> nesta primeira versao; company:<id> fica pra depois.
     *
     * IMPORTANTE: NAO reusa Servidor::ipAllowed() aqui de proposito -- esse
     * metodo libera qualquer IP quando ip_restriction_enabled=false (regra
     * pensada pro endpoint HTTP, que tem o token na URL como segredo real).
     * O primary em UDP nao tem token nenhum -- o IP e a UNICA barreira, tem
     * que ser sempre um match exato contra allowedIps, sem excecao. Um bug
     * de auditoria (29/09/2026) achou isso servindo pra IP desconhecido
     * quando reusava ipAllowed() -- corrigido aqui com match direto.
     */
    private function resolveZoneForIp(string $ip): ?RpzZone
    {
        $servidor = Servidor::query()
            ->where('status', 'active')
            ->whereHas('allowedIps', fn ($q) => $q->where('status', 'active'))
            ->with(['allowedIps' => fn ($q) => $q->where('status', 'active'), 'empresa'])
            ->get()
            ->first(fn (Servidor $s): bool => $s->allowedIps
                ->contains(fn ($regra): bool => Servidor::ipMatchesCidr($ip, $regra->ip_cidr)));

        if ($servidor === null || ! $servidor->empresa?->possuiLicencaAtiva()) {
            return null;
        }

        return RpzZone::where('zone_key', 'server:'.$servidor->id)->first();
    }

    /**
     * @return array{id:int, qname:string, qtype:int, qclass:int}|null
     */
    private function parseQuery(string $packet): ?array
    {
        if (strlen($packet) < 12) {
            return null;
        }

        $header = unpack('nid/nflags/nqdcount/nancount/nnscount/narcount', substr($packet, 0, 12));
        if ($header === false || $header['qdcount'] < 1) {
            return null;
        }

        $offset = 12;
        $qname = $this->decodeName($packet, $offset);
        if ($qname === null || strlen($packet) < $offset + 4) {
            return null;
        }

        $qtypeClass = unpack('nqtype/nqclass', substr($packet, $offset, 4));
        if ($qtypeClass === false) {
            return null;
        }

        return [
            'id' => $header['id'],
            'qname' => $qname,
            'qtype' => $qtypeClass['qtype'],
            'qclass' => $qtypeClass['qclass'],
        ];
    }

    /**
     * Decodifica um nome no formato DNS (labels prefixados por tamanho,
     * terminados em 0x00). Suporta ponteiro de compressao (0xC0) por
     * seguranca, embora a pergunta de um probe SOA normalmente nao use.
     * Limite de saltos pra nunca entrar em loop com um pacote malicioso.
     */
    private function decodeName(string $packet, int &$offset): ?string
    {
        $labels = [];
        $saltos = 0;
        $posOriginal = null;

        while (true) {
            if ($offset >= strlen($packet) || $saltos > 20) {
                return null;
            }

            $len = ord($packet[$offset]);

            if ($len === 0) {
                $offset++;
                break;
            }

            if (($len & 0xC0) === 0xC0) {
                if ($offset + 1 >= strlen($packet)) {
                    return null;
                }
                $ponteiro = unpack('n', substr($packet, $offset, 2))[1] & 0x3FFF;
                if ($posOriginal === null) {
                    $posOriginal = $offset + 2;
                }
                $offset = $ponteiro;
                $saltos++;

                continue;
            }

            if ($offset + 1 + $len > strlen($packet)) {
                return null;
            }

            $labels[] = substr($packet, $offset + 1, $len);
            $offset += 1 + $len;
        }

        if ($posOriginal !== null) {
            $offset = $posOriginal;
        }

        return implode('.', $labels);
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

    private function buildSoaResponse(
        int $id,
        string $qname,
        int $qtype,
        int $qclass,
        int $serial,
        int $refresh,
        int $retry,
        int $expire,
        int $minimum,
        int $ttl,
        string $mname,
        string $rname,
    ): string {
        // Flags: QR=1 (resposta), Opcode=0, AA=1 (autoritativo), TC=0, RD=0,
        // RA=0, Z=0, RCODE=0 (sem erro) -> 1000 0100 0000 0000 = 0x8400.
        $flags = 0x8400;

        $header = pack('nnnnnn', $id, $flags, 1, 1, 0, 0);

        $questionName = $this->encodeName($qname);
        $question = $questionName.pack('nn', $qtype, $qclass);

        $rdata = $this->encodeName($mname).$this->encodeName($rname)
            .pack('NNNNN', $serial, $refresh, $retry, $expire, $minimum);

        // Nome da resposta aponta por compressao pro qname logo apos o
        // cabecalho de 12 bytes (0xC00C) -- sempre valido aqui porque o
        // qname sempre comeca exatamente nesse offset nesta implementacao.
        $answer = "\xC0\x0C".pack('nnNn', self::TYPE_SOA, self::CLASS_IN, $ttl, strlen($rdata)).$rdata;

        return $header.$question.$answer;
    }
}
