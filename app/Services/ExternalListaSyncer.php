<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Dominio;
use App\Models\Lista;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ExternalListaSyncer
{
    public const MIN_DOMAINS_ESPERADOS = 100;

    private const CHUNK_SIZE = 400;

    public function __construct(private DomainNormalizer $normalizer) {}

    /**
     * @return array{status: string, total?: int, adicionados?: int, removidos?: int, motivo?: string}
     */
    public function sync(Lista $lista): array
    {
        if (! $lista->isExterna() || ! $lista->fonte_url) {
            return ['status' => 'ignorada', 'motivo' => 'lista não é externa ou não tem fonte_url configurada'];
        }

        if (! $lista->sync_ativo) {
            return ['status' => 'pausada'];
        }

        try {
            $response = Http::timeout(30)->get($lista->fonte_url);
        } catch (\Throwable $e) {
            return ['status' => 'erro', 'motivo' => 'falha ao buscar o feed: ' . $e->getMessage()];
        }

        if (! $response->successful()) {
            return ['status' => 'erro', 'motivo' => 'feed respondeu com status ' . $response->status()];
        }

        $dominios = $this->parseFeed($response->body(), $lista->fonte_formato ?? 'hostfile');

        if (count($dominios) < self::MIN_DOMAINS_ESPERADOS) {
            return [
                'status' => 'erro',
                'motivo' => 'feed retornou apenas ' . count($dominios) . ' domínios (mínimo esperado ' . self::MIN_DOMAINS_ESPERADOS . ') — abortando para não esvaziar a lista por engano',
            ];
        }

        $existentesAtivos = $lista->dominios()->where('ativo', true)->pluck('dominio')->all();
        $paraDesativar = array_diff($existentesAtivos, $dominios);

        $agora = now();
        $adicionados = 0;

        DB::transaction(function () use ($lista, $dominios, $paraDesativar, $agora, &$adicionados) {
            $existentes = $lista->dominios()->pluck('dominio')->flip();

            foreach (array_chunk($dominios, self::CHUNK_SIZE) as $chunk) {
                $rows = [];
                foreach ($chunk as $dominio) {
                    if (! $existentes->has($dominio)) {
                        $adicionados++;
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
        });

        return [
            'status' => 'ok',
            'total' => count($dominios),
            'adicionados' => $adicionados,
            'removidos' => count($paraDesativar),
        ];
    }

    public function syncAndLog(Lista $lista): array
    {
        $resultado = $this->sync($lista);

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
}
