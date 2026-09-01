<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Lista;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AnatelLegacyFeedSyncer
{
    private const CHUNK_SIZE = 400;

    public function __construct(
        private ExternalListaSyncer $externalSyncer,
        private AnatelExclusionMatcher $exclusionMatcher,
    ) {}

    /**
     * Incorpora o arquivo antigo do Unbound sem remover dominios adicionados
     * pelo fluxo de PDFs da ANATEL.
     *
     * @return array{status: string, total?: int, adicionados?: int, reativados?: int, excluidos?: int, motivo?: string}
     */
    public function sync(Lista $lista): array
    {
        if (! $lista->isAnatel()) {
            return ['status' => 'ignorada', 'motivo' => 'lista não é a ANATEL principal'];
        }

        $url = config('anatel.legacy_feed_url');
        if (! filled($url)) {
            return ['status' => 'ignorada', 'motivo' => 'feed legado não configurado'];
        }

        try {
            $response = Http::timeout(30)->get($url);
        } catch (\Throwable $e) {
            return ['status' => 'erro', 'motivo' => 'falha ao buscar o feed legado: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return ['status' => 'erro', 'motivo' => 'feed legado respondeu com status '.$response->status()];
        }

        $dominios = $this->externalSyncer->parseFeed($response->body(), 'unbound_local_zone');
        if (count($dominios) < ExternalListaSyncer::MIN_DOMAINS_ESPERADOS) {
            return ['status' => 'erro', 'motivo' => 'feed legado retornou apenas '.count($dominios).' domínios; nenhuma alteração aplicada'];
        }

        $exclusoes = $lista->anatelExclusions()->where('active', true)->get();
        $agora = now();
        $adicionados = 0;
        $reativados = 0;
        $excluidos = 0;

        DB::transaction(function () use ($lista, $dominios, $exclusoes, $agora, &$adicionados, &$reativados, &$excluidos): void {
            foreach (array_chunk($dominios, self::CHUNK_SIZE) as $chunk) {
                $existentes = DB::table('dominios')
                    ->where('lista_id', $lista->id)
                    ->whereIn('dominio', $chunk)
                    ->get()
                    ->keyBy('dominio');
                $novos = [];

                foreach ($chunk as $dominio) {
                    $existente = $existentes->get($dominio);
                    if ($this->exclusionMatcher->matches($dominio, $exclusoes)) {
                        $excluidos++;

                        continue;
                    }

                    if (! $existente) {
                        $adicionados++;
                        $novos[] = [
                            'lista_id' => $lista->id,
                            'dominio' => $dominio,
                            'ativo' => true,
                            'inactive_reason' => null,
                            'last_anatel_import_id' => null,
                            'created_at' => $agora,
                            'updated_at' => $agora,
                        ];
                    } elseif (! $existente->ativo && $existente->inactive_reason !== 'anatel_exclusion') {
                        $reativados++;
                        DB::table('dominios')->where('id', $existente->id)->update([
                            'ativo' => true,
                            'inactive_reason' => null,
                            'updated_at' => $agora,
                        ]);
                    }
                }

                if ($novos !== []) {
                    DB::table('dominios')->insert($novos);
                }
            }

            $lista->forceFill(['last_sync_at' => $agora])->save();
        });

        return [
            'status' => 'ok',
            'total' => count($dominios),
            'adicionados' => $adicionados,
            'reativados' => $reativados,
            'excluidos' => $excluidos,
        ];
    }

    public function syncAndLog(Lista $lista): array
    {
        $resultado = $this->sync($lista);
        $descricao = $resultado['status'] === 'ok'
            ? "Feed legado incorporado à ANATEL principal: {$resultado['total']} válidos, {$resultado['adicionados']} novos, {$resultado['reativados']} reativados, {$resultado['excluidos']} excluídos"
            : "Sincronização do feed legado ANATEL falhou: {$resultado['motivo']}";

        if (in_array($resultado['status'], ['ok', 'erro'], true)) {
            AuditLog::create([
                'action' => $resultado['status'] === 'ok' ? 'anatel.legacy_feed.synced' : 'anatel.legacy_feed.failed',
                'description' => $descricao,
                'created_at' => now(),
            ]);
        }

        return $resultado;
    }
}
