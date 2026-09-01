<?php

namespace App\Services;

use App\Models\Lista;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RpzZoneBuilder
{
    public const TTL = 60;

    public function __construct(private DomainNormalizer $normalizer) {}

    public function panelHost(): string
    {
        return parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
    }

    public function target(string $mode = 'nxdomain'): string
    {
        return $mode === 'redirect' ? rtrim($this->panelHost(), '.').'.' : '.';
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

    /** @return \Generator<int, string> */
    public function lines(Builder $query, string $target = '.', ?string $serial = null): \Generator
    {
        $host = $this->panelHost();
        $serial ??= (string) now()->timestamp;

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
            if ($domain === null || $domain === $previous) {
                continue;
            }
            $previous = $domain;
            yield $domain.' CNAME '.$target;
            yield '*.'.$domain.' CNAME '.$target;
        }
    }

    public function build(Builder $query, string $target = '.', ?string $serial = null): string
    {
        $content = '';
        foreach ($this->lines($query, $target, $serial) as $line) {
            $content .= $line."\n";
        }
        return $content;
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
