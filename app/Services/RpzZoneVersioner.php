<?php

namespace App\Services;

use App\Models\RpzZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Persistencia do estado de versao de cada zona logica (RPZ-SERIAL-1,
 * auditoria tecnica de 29/09/2026). Responsavel apenas por decidir e gravar
 * serial/revision/content_hash -- a construcao do conteudo em si continua
 * em RpzZoneBuilder.
 */
class RpzZoneVersioner
{
    /** Limite de um campo SOA serial de 32 bits (RFC 1982). */
    public const SERIAL_MAX = 4294967295;

    public function zoneKey(string $scopeType, int $scopeId): string
    {
        return $scopeType.':'.$scopeId;
    }

    /**
     * Resolve o estado persistido de uma zona logica: cria na primeira vez
     * (bootstrap, ver $legacyBootstrapSerial), mantem serial/revision quando
     * o conteudo nao mudou, ou avanca (+1 serial, +1 revision) quando mudou.
     *
     * "Nao mudou" e decidido pelo content_hash (payload canonico, serial
     * neutralizado) -- nunca pelo serial em si, que e so a saida.
     *
     * CONCORRENCIA (auditado em 29/09/2026, validado com 2 processos PHP
     * reais concorrentes contra o mesmo arquivo SQLite -- nao so PHPUnit):
     *
     * O lockForUpdate() abaixo e cosmetico neste banco: o grammar SQLite do
     * Laravel implementa compileLock() retornando string vazia (ver
     * vendor/laravel/framework/.../Query/Grammars/SQLiteGrammar.php), entao
     * NENHUMA clausula de lock e de fato enviada ao SQLite. Ele fica no
     * codigo só pra nao quebrar se um dia a conexao mudar pra MySQL/Postgres,
     * onde vira um SELECT ... FOR UPDATE de verdade.
     *
     * A correcao real neste banco vem de outro lugar: SQLite so permite UM
     * escritor por vez no arquivo inteiro (nao ha lock por linha). A conexao
     * sqlite deste projeto usa busy_timeout=10000ms e journal_mode=WAL
     * (config/database.php) -- ou seja, quando duas transacoes concorrentes
     * tentam escrever ao mesmo tempo, o PROPRIO SQLite bloqueia uma delas por
     * ate 10s esperando a outra liberar, antes de sequer cogitar devolver
     * "database is locked". O withRetry() abaixo so entra em acao no raro
     * caso de estourar esse busy_timeout.
     *
     * Como o retry refaz a transacao INTEIRA (SELECT + decisao + UPDATE), uma
     * transacao que perde a corrida sempre re-le o estado ja commitado pela
     * vencedora antes de decidir seu proximo passo -- por isso os 3 cenarios
     * testados convergem sem duplicar revision nem perder incremento:
     *   A) zona nova, mesmo conteudo nas duas requisicoes -> 1 unica linha
     *      criada, ambas retornam o mesmo serial/revision (a 2a apenas re-le
     *      a que a 1a ja criou).
     *   B) zona existente, mudanca pro mesmo conteudo novo nas duas -> avanca
     *      revision uma unica vez, as duas convergem no mesmo resultado.
     *   C) zona existente, duas mudancas DIFERENTES concorrentes -> serializa
     *      em duas revisions sequenciais (nao corrompe, nao perde nenhuma das
     *      duas mudancas, so nao acontecem "ao mesmo tempo" de verdade).
     * Nenhum dos 6 processos de teste (2 por cenario) recebeu excecao nao
     * tratada nem gerou linha duplicada em rpz_zones.
     */
    public function resolveVersion(
        string $zoneKey,
        string $scopeType,
        ?int $empresaId,
        ?int $servidorId,
        string $zoneName,
        string $contentHash,
        int $domainCount,
        int $ruleCount,
        int $excludedCount,
        \Closure $legacyBootstrapSerial,
    ): RpzZone {
        return $this->withRetry(function () use (
            $zoneKey, $scopeType, $empresaId, $servidorId, $zoneName,
            $contentHash, $domainCount, $ruleCount, $excludedCount, $legacyBootstrapSerial,
        ) {
            return DB::transaction(function () use (
                $zoneKey, $scopeType, $empresaId, $servidorId, $zoneName,
                $contentHash, $domainCount, $ruleCount, $excludedCount, $legacyBootstrapSerial,
            ) {
                $zone = RpzZone::where('zone_key', $zoneKey)->lockForUpdate()->first();

                if ($zone === null) {
                    return RpzZone::create([
                        'zone_key' => $zoneKey,
                        'scope_type' => $scopeType,
                        'empresa_id' => $empresaId,
                        'servidor_id' => $servidorId,
                        'zone_name' => $zoneName,
                        // Bootstrap: usa o CRC32b legado apenas como ponto de partida,
                        // pra nao "regredir" o serial ja distribuido aos resolvers.
                        // A partir daqui o CRC nunca mais decide o serial.
                        'last_serial' => $legacyBootstrapSerial(),
                        'revision' => 1,
                        'content_hash' => $contentHash,
                        'domain_count' => $domainCount,
                        'rule_count' => $ruleCount,
                        'excluded_count' => $excludedCount,
                        'generated_at' => now(),
                    ]);
                }

                if ($zone->content_hash === $contentHash) {
                    $zone->forceFill([
                        'domain_count' => $domainCount,
                        'rule_count' => $ruleCount,
                        'excluded_count' => $excludedCount,
                        'generated_at' => now(),
                    ])->save();

                    return $zone;
                }

                $zone->forceFill([
                    'last_serial' => $this->nextSerial((int) $zone->last_serial),
                    'revision' => $zone->revision + 1,
                    'content_hash' => $contentHash,
                    'domain_count' => $domainCount,
                    'rule_count' => $ruleCount,
                    'excluded_count' => $excludedCount,
                    'generated_at' => now(),
                ])->save();

                return $zone;
            });
        });
    }

    /**
     * Avanca o serial em +1 por mudanca efetiva de conteudo -- nao por
     * requisicao. Decisao desta fase (RPZ-SERIAL-1): bloquear com erro
     * controlado ao atingir o limite de 32 bits em vez de dar wrap
     * silencioso (RFC 1982 define comparacao modular de serial, mas nao
     * cobrimos aqui a logica de wrap seguro -- ver auditoria, secao 14).
     * Nao esperamos atingir esse limite em uso normal: exigiria mais de 4
     * bilhoes de mudancas de conteudo numa unica zona logica.
     */
    public function nextSerial(int $current): int
    {
        if ($current >= self::SERIAL_MAX) {
            throw new \RuntimeException(
                "Serial RPZ atingiu o limite de 32 bits ({$current}); intervenção manual necessária antes de publicar nova versão (RFC 1982)."
            );
        }

        return $current + 1;
    }

    /**
     * SQLite nao tem lock de linha real (sem SELECT ... FOR UPDATE de
     * verdade); sob concorrencia, "database is locked" e esperado
     * ocasionalmente mesmo dentro de uma transacao. Retry curto documentado
     * em vez de assumir que lockForUpdate() sozinho resolve nesse banco.
     */
    private function withRetry(\Closure $callback, int $attempts = 3): mixed
    {
        for ($tentativa = 1; $tentativa <= $attempts; $tentativa++) {
            try {
                return $callback();
            } catch (QueryException $e) {
                $ultima = $tentativa === $attempts;
                $isLockError = str_contains($e->getMessage(), 'database is locked')
                    || str_contains($e->getMessage(), 'SQLSTATE[HY000]: General error: 5');

                if ($ultima || ! $isLockError) {
                    throw $e;
                }

                usleep(50_000);
            }
        }

        throw new \LogicException('unreachable');
    }
}
