<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Dominio;
use App\Models\Lista;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\IpUtils;

class ExternalListaSyncer
{
    public const MIN_DOMAINS_ESPERADOS = 100;

    private const CHUNK_SIZE = 400;

    public function __construct(private DomainNormalizer $normalizer, private RpzDirtyMarker $rpzDirty) {}

    /**
     * @return array{status: string, total?: int, adicionados?: int, removidos?: int, motivo?: string}
     */
    public function sync(Lista $lista): array
    {
        if (! config('lists.external_sync_enabled', true)) {
            return ['status' => 'pausada', 'motivo' => 'sincronização externa desabilitada neste ambiente'];
        }
        if (! $lista->isExterna() || ! $lista->fonte_url) {
            return ['status' => 'ignorada', 'motivo' => 'lista não é externa ou não tem fonte_url configurada'];
        }

        if (! $lista->sync_ativo) {
            return ['status' => 'pausada'];
        }

        $addresses = $this->publicFeedAddresses($lista->fonte_url);
        if ($addresses === null) {
            return ['status' => 'erro', 'motivo' => 'URL do feed aponta para uma rede local ou reservada'];
        }

        $lock = Cache::lock('external-lista-sync:'.$lista->id, 300);
        if (! $lock->get()) {
            return ['status' => 'erro', 'motivo' => 'já existe uma sincronização desta lista em andamento'];
        }

        try {
            $maxFeedBytes = max(1, (int) config('lists.external_feed_max_bytes', 134217728));
            $host = trim(strtolower((string) parse_url($lista->fonte_url, PHP_URL_HOST)), '[]');
            $port = parse_url($lista->fonte_url, PHP_URL_PORT)
                ?: (strtolower((string) parse_url($lista->fonte_url, PHP_URL_SCHEME)) === 'https' ? 443 : 80);
            $options = [
                // Do not resolve the hostname again after checking its IPs.
                // Bypass environment proxies, which could resolve it independently.
                'proxy' => '',
                'decode_content' => false,
                'on_headers' => static function (ResponseInterface $response) use ($maxFeedBytes): void {
                    $encoding = strtolower(trim($response->getHeaderLine('Content-Encoding')));
                    if ($encoding !== '' && $encoding !== 'identity') {
                        throw new \UnexpectedValueException('Compressed feeds are not accepted');
                    }
                    if ((int) $response->getHeaderLine('Content-Length') > $maxFeedBytes) {
                        throw new \LengthException('Feed exceeds maximum size');
                    }
                },
                'progress' => static function ($total, $downloaded) use ($maxFeedBytes): void {
                    if ($total > $maxFeedBytes || $downloaded > $maxFeedBytes) {
                        throw new \LengthException('Feed exceeds maximum size');
                    }
                },
            ];
            if ($addresses !== [] && ! filter_var($host, FILTER_VALIDATE_IP)) {
                $destinations = array_map(static fn ($ip) => str_contains($ip, ':') ? '['.$ip.']' : $ip, $addresses);
                $options['curl'][CURLOPT_RESOLVE] = [$host.':'.$port.':'.implode(',', $destinations)];
            }
            $response = Http::connectTimeout(10)
                ->timeout(30)
                ->withoutRedirecting()
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->withOptions($options)
                ->get($lista->fonte_url);
        } catch (\Throwable $e) {
            $lock->release();

            return ['status' => 'erro', 'motivo' => 'falha ao buscar o feed com segurança'];
        }

        try {
        if (! $response->successful()) {
            if ($response->redirect()) {
                return ['status' => 'erro', 'motivo' => 'redirects não são permitidos para feeds externos'];
            }
            return ['status' => 'erro', 'motivo' => 'feed respondeu com status '.$response->status()];
        }

        $contentLength = $response->header('Content-Length');
        $maxFeedBytes = max(1, (int) config('lists.external_feed_max_bytes', 134217728));
        if ($contentLength !== null && (int) $contentLength > $maxFeedBytes) {
            return ['status' => 'erro', 'motivo' => 'feed excede o tamanho máximo permitido'];
        }

        $body = $response->body();
        if (strlen($body) > $maxFeedBytes) {
            return ['status' => 'erro', 'motivo' => 'feed excede o tamanho máximo permitido'];
        }

        $dominios = $this->parseFeed($body, $lista->fonte_formato ?? 'hostfile');

        if (count($dominios) < self::MIN_DOMAINS_ESPERADOS) {
            return [
                'status' => 'erro',
                'motivo' => 'feed retornou apenas '.count($dominios).' domínios (mínimo esperado '.self::MIN_DOMAINS_ESPERADOS.') — abortando para não esvaziar a lista por engano',
            ];
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            return app(SqliteDomainFeedWriter::class)->publish($lista, $dominios, true);
        }

        $existentesAtivos = $lista->dominios()->where('ativo', true)->pluck('dominio')->all();
        $paraDesativar = array_diff($existentesAtivos, $dominios);

        $agora = now();
        $adicionados = 0;
        $reativados = 0;

        DB::transaction(function () use ($lista, $dominios, $paraDesativar, $agora, &$adicionados, &$reativados) {
            // SQLite may retry after a competing writer. Counters describe
            // the committed attempt, never an earlier rolled-back attempt.
            $adicionados = 0;
            $reativados = 0;

            foreach (array_chunk($dominios, self::CHUNK_SIZE) as $chunk) {
                // Fetch only this batch. Loading the whole existing feed as
                // keyed rows exceeded 256 MiB for the recovered large list.
                $existentes = $lista->dominios()->whereIn('dominio', $chunk)->pluck('ativo', 'dominio');
                $rows = [];
                foreach ($chunk as $dominio) {
                    if (! $existentes->has($dominio)) {
                        $adicionados++;
                    } elseif (! $existentes->get($dominio)) {
                        $reativados++;
                    }
                    $rows[] = [
                        'lista_id' => $lista->id,
                        'dominio' => $dominio,
                        'ativo' => true,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];
                }
                DB::table('dominios')->upsert($rows, ['lista_id', 'dominio'], ['ativo', 'updated_at']);
            }

            foreach (array_chunk($paraDesativar, self::CHUNK_SIZE) as $chunk) {
                Dominio::where('lista_id', $lista->id)
                    ->whereIn('dominio', $chunk)
                    ->update(['ativo' => false, 'updated_at' => $agora]);
            }

            $lista->forceFill(['last_sync_at' => $agora])->save();
        }, 5);

        if ($adicionados > 0 || $reativados > 0 || $paraDesativar !== []) {
            $this->rpzDirty->markForList($lista->id);
        }

        return [
            'status' => 'ok',
            'total' => count($dominios),
            'adicionados' => $adicionados,
            'removidos' => count($paraDesativar),
        ];
        } finally {
            $lock->release();
        }
    }

    public function syncAndLog(Lista $lista): array
    {
        try {
            $resultado = $this->sync($lista);
        } catch (\Illuminate\Database\QueryException $exception) {
            // Preserve the previous transaction and let the command continue
            // with other sources. Never expose SQL/domain batches to the UI.
            $resultado = ['status' => 'erro', 'motivo' => 'falha ao atualizar o banco; tente sincronizar novamente'];
        }

        if ($resultado['status'] === 'ok') {
            AuditLog::create([
                'user_id' => null,
                'empresa_id' => null,
                'action' => 'lista.externa.sync',
                'description' => "Lista externa \"{$lista->nome}\" sincronizada: {$resultado['total']} ativos no feed, {$resultado['adicionados']} novos, {$resultado['removidos']} desativados",
                'ip_address' => null,
                'created_at' => now(),
            ]);
        } elseif ($resultado['status'] === 'erro') {
            AuditLog::create([
                'user_id' => null,
                'empresa_id' => null,
                'action' => 'lista.externa.sync_falhou',
                'description' => "Sincronização da lista externa \"{$lista->nome}\" falhou: {$resultado['motivo']}",
                'ip_address' => null,
                'created_at' => now(),
            ]);
        }

        return $resultado;
    }

    /**
     * @return array<int, string>
     */
    public function parseFeed(string $body, string $formato): array
    {
        return match ($formato) {
            'plain' => $this->parsePlain($body),
            'unbound_local_zone' => $this->parseUnboundLocalZone($body),
            default => $this->parseHostfile($body),
        };
    }

    /**
     * @return array<int, string>
     */
    private function parseHostfile(string $body): array
    {
        $dominios = [];

        foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 2) {
                continue;
            }

            $dominio = $this->normalize($parts[1]);
            if ($dominio !== null) {
                $dominios[$dominio] = true;
            }
        }

        return array_keys($dominios);
    }

    /**
     * @return array<int, string>
     */
    private function parsePlain(string $body): array
    {
        $dominios = [];

        foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $dominio = $this->normalize($line);
            if ($dominio !== null) {
                $dominios[$dominio] = true;
            }
        }

        return array_keys($dominios);
    }

    /**
     * Formato nativo do Unbound: blocos "local-zone: "dominio" redirect" +
     * linhas local-data. Le so a linha local-zone (a fonte da verdade do
     * dominio bloqueado) e ignora local-data (redundante pro nosso uso).
     *
     * @return array<int, string>
     */
    private function parseUnboundLocalZone(string $body): array
    {
        $dominios = [];

        foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
            $line = trim($line);

            if (! str_starts_with($line, 'local-zone:')) {
                continue;
            }

            if (! preg_match('/local-zone:\s*"([^"]+)"/', $line, $matches)) {
                continue;
            }

            $dominio = $this->normalize($matches[1]);
            if ($dominio !== null) {
                $dominios[$dominio] = true;
            }
        }

        return array_keys($dominios);
    }

    private function normalize(string $value): ?string
    {
        return $this->normalizer->normalize($value, false);
    }

    /** @return list<string>|null Null rejects the URL; [] is only for synthetic .example tests. */
    protected function publicFeedAddresses(string $url): ?array
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = trim(strtolower((string) parse_url($url, PHP_URL_HOST)), '[]');
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            return null;
        }

        if (app()->environment('testing') && str_ends_with($host, '.example')) {
            return [];
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : [];
        if ($addresses === []) {
            foreach ($this->feedDnsRecords($host) as $record) {
                $addresses[] = $record['ip'] ?? $record['ipv6'] ?? '';
            }
        }

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || IpUtils::checkIp($address, [
                    '100.64.0.0/10', '224.0.0.0/4', '::/96', '::ffff:0:0/96',
                    '64:ff9b::/96', '64:ff9b:1::/48', '2002::/16', 'ff00::/8',
                ])) {
                return null;
            }
        }

        return array_values(array_unique($addresses));
    }

    protected function feedDnsRecords(string $host): array
    {
        return dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
    }
}
