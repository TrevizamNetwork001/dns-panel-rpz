<?php

namespace App\Services;

use App\Models\Lista;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RpzZoneBuilder
{
    public const TTL = 60;

    /** Bytes em RAM antes de buildCanonical() passar a gravar em disco temporário. */
    public const CANONICAL_STREAM_MEMORY_THRESHOLD = 2 * 1024 * 1024;

    /** Tamanho do bloco de leitura ao transmitir o stream em renderZone(). */
    public const RENDER_CHUNK_SIZE = 65536;

    public function __construct(private DomainNormalizer $normalizer) {}

    public function panelHost(): string
    {
        return parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
    }

    public function target(string $mode = 'nxdomain'): string
    {
        return $mode === 'redirect' ? rtrim($this->panelHost(), '.').'.' : '.';
    }

    /**
     * Refresh do SOA em segundos. Fixo em 3600s pra toda zona real, exceto
     * uma unica excecao temporaria: server:15 (DNS-03) e o servidor de
     * laboratorio do RPZ-UNBOUND-LAB-1 (29/09/2026), isolado com uma unica
     * lista sintetica (sem conteudo real de bloqueio), usado para testar o
     * ciclo de refresh do Unbound sem esperar 1h por iteracao. Reverter para
     * `return 3600;` incondicional ao encerrar o laboratorio.
     */
    public function refreshFor(string $zoneKey): int
    {
        return $zoneKey === 'server:15' ? 300 : 3600;
    }

    public function listQuery(Lista $lista): Builder
    {
        return DB::table('dominios')
            ->where('lista_id', $lista->id)
            ->where('ativo', true)
            ->selectRaw('LOWER(dominio) as dominio')
            ->distinct()
            ->orderBy('dominio');
    }

    public function serverQuery(int $servidorId): Builder
    {
        return DB::table('dominios')
            ->join('lista_servidor', 'lista_servidor.lista_id', '=', 'dominios.lista_id')
            ->join('listas', 'listas.id', '=', 'lista_servidor.lista_id')
            ->where('lista_servidor.servidor_id', $servidorId)
            ->where('listas.status', 'active')
            ->where('dominios.ativo', true)
            ->selectRaw('LOWER(dominios.dominio) as dominio')
            ->distinct()
            ->orderBy('dominio');
    }

    public function companyQuery(int $empresaId): Builder
    {
        return DB::table('dominios')
            ->join('lista_servidor', 'lista_servidor.lista_id', '=', 'dominios.lista_id')
            ->join('servidores', 'servidores.id', '=', 'lista_servidor.servidor_id')
            ->join('listas', 'listas.id', '=', 'lista_servidor.lista_id')
            ->where('servidores.empresa_id', $empresaId)
            ->where('servidores.status', 'active')
            ->where('listas.status', 'active')
            ->where('dominios.ativo', true)
            ->selectRaw('LOWER(dominios.dominio) as dominio')
            ->distinct()
            ->orderBy('dominio');
    }

    /** @return array{total:int,active:int,inactive:int,excluded:int,duplicates:int,entries:int,invalid:int} */
    public function stats(Lista $lista): array
    {
        $base = DB::table('dominios')->where('lista_id', $lista->id);
        $total = (clone $base)->count();
        $active = (clone $base)->where('ativo', true)->count();
        $entries = 0;
        $invalid = 0;
        $uniqueStored = 0;
        $previous = null;
        foreach ($this->listQuery($lista)->cursor() as $row) {
            $uniqueStored++;
            $domain = $this->normalizer->normalize((string) $row->dominio, false);
            if ($domain === null) {
                $invalid++;

                continue;
            }
            if ($domain !== $previous) {
                $entries++;
                $previous = $domain;
            }
        }

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'excluded' => (clone $base)->where('inactive_reason', 'anatel_exclusion')->count(),
            'duplicates' => max(0, $active - $uniqueStored),
            'entries' => $entries,
            'invalid' => $invalid,
        ];
    }

    /**
     * Serial derivado do conteudo real da lista de dominios, nao do horario
     * atual -- fica igual entre duas buscas quando nada mudou de verdade.
     *
     * IMPORTANTE (corrigido em 29/09/2026): essa estabilidade NAO faz o
     * Unbound pular a reaplicacao pesada da zona. Isso foi testado ao vivo e
     * refutado -- o Unbound reconstroi a zona a cada download via `url:`
     * independente do serial ter mudado ou nao (ver RUNBOOK.md e auditoria
     * tecnica de 29/09/2026, secao 11). O beneficio real desse metodo e so
     * evitar que o proprio campo `serial` do SOA fique "voltando no tempo"
     * entre requisicoes concorrentes.
     *
     * Mantido como CRC32b legado apenas para o bootstrap do serial
     * monotonico persistido em rpz_zones (ver RpzZoneVersioner) -- nunca
     * mais usado como mecanismo de versionamento continuo depois disso.
     */
    public function computeSerial(Builder $query): string
    {
        $ctx = hash_init('crc32b');
        foreach ((clone $query)->cursor() as $row) {
            hash_update($ctx, (string) $row->dominio);
            hash_update($ctx, "\n");
        }

        return (string) hexdec(hash_final($ctx));
    }

    /**
     * Passagem unica sobre o cursor: aplica normalizacao, filtro de tamanho,
     * deduplicacao adjacente e regra de wildcard/WPAD, devolvendo tanto as
     * linhas de regra efetivamente emitidas quanto as metricas do payload.
     * Serve de fonte unica tanto pro hash de conteudo (contentHash()) quanto
     * pro corpo renderizado (renderZone()) -- elimina o risco, apontado na
     * auditoria de 29/09/2026 (secao 5), de serial calculado sobre um
     * conjunto e corpo servido sobre outro por virem de queries separadas.
     *
     * O corpo emitido aqui e byte-a-byte igual ao que lines()/build() ja
     * produziam para o mesmo $query/$target -- so a origem do serial muda
     * (persistido em vez de recalculado por requisicao). lines()/build() e
     * computeSerial() continuam existindo, inalterados, para os fluxos que
     * ainda nao migraram pro versionamento persistido (preview de lista).
     *
     * MEMORIA (corrigido em 29/09/2026, ver auditoria/benchmark da mesma
     * data): a primeira versao guardava cada regra emitida como elemento de
     * um array PHP (`$lines[] = ...`). Para a maior zona real (~759.697
     * regras) isso mediu ~95MB de overhead SO de estrutura de array, alem
     * dos bytes do conteudo em si -- risco real de OOM sob concorrencia no
     * endpoint empresarial (worker do PHP-FPM com memory_limit=256M, mem
     * cgroup do container em 768M compartilhado entre ate pm.max_children
     * workers). Trocado por um stream (`php://temp`, que fica em RAM ate
     * MAXMEM_ANTES_DE_SPILL e so entao passa a usar arquivo temporario em
     * disco -- nunca array) + hash SHA-256 incremental calculado NA MESMA
     * passada do cursor, sem segunda iteracao. domain_count/rule_count/
     * excluded_count continuam identicos a antes.
     *
     * @return array{stream: resource, content_hash: string, domain_count: int, rule_count: int, excluded_count: int}
     */
    public function buildCanonical(Builder $query, string $zoneHost, string $target, int $refresh = 3600): array
    {
        // 2MB em RAM antes de comecar a gravar em arquivo temporario real
        // (caminho gerenciado pelo proprio PHP, nao previsivel/controlavel
        // pelo usuario). Zonas pequenas/medias (a maioria) nunca tocam
        // disco; so a zona grande da empresa spilla, e mesmo assim so o
        // excedente acima de 2MB.
        $stream = fopen('php://temp/maxmemory:'.self::CANONICAL_STREAM_MEMORY_THRESHOLD, 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Nao foi possivel abrir stream temporario para renderizar a zona RPZ.');
        }

        $hashCtx = hash_init('sha256');
        // Cabecalho do hash: tudo que afeta o arquivo final que o CRC32b
        // legado ignorava (TTL/temporizadores do SOA, canario, destino) --
        // com o serial neutralizado (omitido por completo, nao zerado).
        // Canario e sempre "CNAME ." (nunca segue $target/redirect), igual
        // ao emitido mais abaixo e em lines().
        $canario = 'blocktest.'.$zoneHost.' CNAME .';
        $cabecalhoHash = implode('|', [
            $zoneHost,
            self::TTL,
            $refresh,  // refresh
            600,   // retry
            86400, // expire
            60,    // minimum
            'NS localhost.',
            $target,
            $canario,
        ]);
        hash_update($hashCtx, $cabecalhoHash);
        hash_update($hashCtx, "\n");

        $domainCount = 0;
        $ruleCount = 0;
        $excludedCount = 0;
        $previous = null;

        // Acumula em um buffer local (string) e só grava/hasheia em blocos --
        // chamar fwrite()/hash_update() uma vez por regra (759 mil vezes numa
        // zona grande) tem overhead de chamada mensuravel; em blocos de ~64KB
        // isso cai pra algumas centenas de chamadas, sem voltar a guardar tudo
        // em memoria (o buffer nunca cresce alem do limiar abaixo). Cada linha
        // do buffer ja sai com seu "\n", entao o mesmo buffer serve pro stream
        // e pro hash sem formatos diferentes.
        $buffer = '';
        $bufferBytes = 0;

        foreach ($query->cursor() as $row) {
            $domainCount++;
            $domain = $this->normalizer->normalize((string) $row->dominio, false);
            if ($domain === null || $domain === $previous) {
                continue;
            }
            if (! $this->ownerFitsZone($domain, $zoneHost)) {
                $excludedCount++;

                continue;
            }
            $previous = $domain;

            $linha = $domain.' CNAME '.$target."\n";
            $buffer .= $linha;
            $bufferBytes += strlen($linha);
            $ruleCount++;

            // WPAD: bloquear apenas este FQDN, sem ampliar a regra a subdomínios.
            if ($domain !== 'wpad.domain.name' && $this->ownerFitsZone('*.'.$domain, $zoneHost)) {
                $wildcard = '*.'.$domain.' CNAME '.$target."\n";
                $buffer .= $wildcard;
                $bufferBytes += strlen($wildcard);
                $ruleCount++;
            }

            if ($bufferBytes >= self::RENDER_CHUNK_SIZE) {
                fwrite($stream, $buffer);
                hash_update($hashCtx, $buffer);
                $buffer = '';
                $bufferBytes = 0;
            }
        }

        if ($buffer !== '') {
            fwrite($stream, $buffer);
            hash_update($hashCtx, $buffer);
        }

        return [
            'stream' => $stream,
            'content_hash' => hash_final($hashCtx),
            'domain_count' => $domainCount,
            'rule_count' => $ruleCount,
            'excluded_count' => $excludedCount,
        ];
    }

    /**
     * Renderiza o zonefile a partir de um payload canonico ja calculado
     * (buildCanonical()) e do serial persistido -- sem nova consulta ao
     * banco e sem reler o array de regras (nao existe mais). Le o stream em
     * blocos (nunca o conteudo inteiro de uma vez) para manter o streaming
     * real na resposta HTTP do endpoint empresarial. Formato identico ao de
     * lines(), byte a byte -- so a origem do serial muda.
     *
     * IMPORTANTE: cada valor yielded aqui ja vem com toda a quebra de linha
     * que precisa; quem consome este generator deve apenas concatenar/
     * transmitir os pedaços na ordem, sem acrescentar "\n" por fora (ao
     * contrário do lines() antigo, que yielda uma "linha" por vez sem \n).
     *
     * @param  array{stream: resource, content_hash: string, domain_count: int, rule_count: int, excluded_count: int}  $canonical
     * @return \Generator<int, string>
     */
    public function renderZone(array $canonical, string $zoneHost, string $serial, string $target, int $refresh = 3600): \Generator
    {
        $cabecalho = [
            '$TTL '.self::TTL,
            '@ SOA '.$zoneHost.'. hostmaster.'.$zoneHost.'. (',
            '    '.$serial.'  ; serial',
            '    '.$refresh.'           ; refresh',
            '    600            ; retry',
            '    86400          ; expire',
            '    60 )           ; minimum',
            '  NS localhost.',
            '',
            '; dominio canario -- sempre presente, usado para testar a sincronizacao',
            'blocktest.'.$zoneHost.' CNAME .',
            '',
        ];
        yield implode("\n", $cabecalho)."\n";

        $stream = $canonical['stream'];
        rewind($stream);
        while (! feof($stream)) {
            $bloco = fread($stream, self::RENDER_CHUNK_SIZE);
            if ($bloco === false || $bloco === '') {
                break;
            }
            yield $bloco;
        }
    }

    /** @return \Generator<int, string> */
    public function lines(Builder $query, string $target = '.', ?string $serial = null): \Generator
    {
        $host = $this->panelHost();
        $serial ??= $this->computeSerial($query);

        yield '$TTL '.self::TTL;
        yield '@ SOA '.$host.'. hostmaster.'.$host.'. (';
        yield '    '.$serial.'  ; serial';
        yield '    3600           ; refresh';
        yield '    600            ; retry';
        yield '    86400          ; expire';
        yield '    60 )           ; minimum';
        yield '  NS localhost.';
        yield '';
        yield '; dominio canario -- sempre presente, usado para testar a sincronizacao';
        yield 'blocktest.'.$host.' CNAME .';
        yield '';

        $previous = null;
        foreach ($query->cursor() as $row) {
            $domain = $this->normalizer->normalize((string) $row->dominio, false);
            if ($domain === null || $domain === $previous || ! $this->ownerFitsZone($domain, $host)) {
                continue;
            }
            $previous = $domain;
            yield $domain.' CNAME '.$target;
            // WPAD: bloquear apenas este FQDN, sem ampliar a regra a subdomínios.
            if ($domain !== 'wpad.domain.name' && $this->ownerFitsZone('*.'.$domain, $host)) {
                yield '*.'.$domain.' CNAME '.$target;
            }
        }
    }

    /**
     * Os nomes no arquivo são relativos à zona RPZ. Portanto, além de o
     * domínio isolado ser válido, domínio + origem da zona precisam caber no
     * limite DNS de 253 caracteres. O Unbound rejeita a zona inteira quando
     * uma única entrada ultrapassa esse limite.
     */
    private function ownerFitsZone(string $owner, string $zone): bool
    {
        return strlen($owner.'.'.rtrim($zone, '.')) <= 253;
    }

    public function build(Builder $query, string $target = '.', ?string $serial = null): string
    {
        $content = '';
        foreach ($this->lines($query, $target, $serial) as $line) {
            $content .= $line."\n";
        }

        return $content;
    }

    /** @return \Generator<int, string> */
    public function hostsLines(Builder $query): \Generator
    {
        yield '# DNS Panel RPZ - feed para MikroTik RouterOS v7 adlist';
        yield '# Gerado em '.now()->toIso8601String();

        $previous = null;
        foreach ($query->cursor() as $row) {
            $domain = $this->normalizer->normalize((string) $row->dominio, false);
            if ($domain === null || $domain === $previous) {
                continue;
            }
            $previous = $domain;
            yield '0.0.0.0 '.$domain;
        }
    }

    /** @return array{content:string,shown:int,truncated:bool,bytes:int,estimated_bytes:int} */
    public function preview(Lista $lista, int $ruleLimit = 500, ?string $serial = null, ?int $totalEntries = null): array
    {
        $content = '';
        $shown = 0;
        $headerLines = 12;

        foreach ($this->lines($this->listQuery($lista), '.', $serial) as $index => $line) {
            if ($index >= $headerLines && $shown >= $ruleLimit) {
                break;
            }
            $content .= $line."\n";
            if ($index >= $headerLines) {
                $shown++;
            }
        }

        $totalRules = ($totalEntries ?? $this->stats($lista)['entries']) * 2;
        $bytes = strlen($content);
        $estimatedBytes = $shown > 0 ? (int) round($bytes * max(1, $totalRules / $shown)) : $bytes;

        return ['content' => $content, 'shown' => $shown, 'truncated' => $totalRules > $shown, 'bytes' => $bytes, 'estimated_bytes' => $estimatedBytes];
    }
}
