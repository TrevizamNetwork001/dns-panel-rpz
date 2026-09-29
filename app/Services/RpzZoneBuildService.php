<?php

namespace App\Services;

use App\Jobs\BuildRpzZone;
use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\RpzZone;
use App\Models\RpzZoneVersion;
use App\Models\Servidor;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Orquestra a geração do artefato .zone fora do GET (RPZ-BUILD-1): resolve a
 * zona lógica, decide se o conteúdo mudou, escreve em staging, valida,
 * publica atomicamente. O GET dinâmico atual (RpzController) continua
 * funcionando exatamente como antes -- este serviço só alimenta um caminho
 * alternativo (current_version), ativado por RPZ_STATIC_ARTIFACTS_ENABLED.
 *
 * CONCORRÊNCIA: flock() por zona é o que garante só 1 build efetivo por vez
 * (não a fila -- BuildRpzZone::dispatch() não é ShouldBeUnique de propósito,
 * ver comentário no Job). desired_generation/built_generation (persistidos)
 * são a fonte durável de verdade de "precisa rebuild"; jobs duplicados viram
 * no-op barato ao checar isso antes de tocar no banco de domínios.
 */
class RpzZoneBuildService
{
    public function __construct(
        private RpzZoneBuilder $builder,
        private RpzZoneVersioner $versioner,
    ) {}

    /**
     * @return array{status: string, zone_key: string, revision?: int, serial?: int}
     */
    public function build(string $zoneKey): array
    {
        $lockPath = $this->lockPath($zoneKey);
        Storage::disk('local')->makeDirectory(dirname($lockPath));
        $lockHandle = fopen(Storage::disk('local')->path($lockPath), 'c');
        if ($lockHandle === false || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($lockHandle !== false) {
                fclose($lockHandle);
            }

            return ['status' => 'skipped_locked', 'zone_key' => $zoneKey];
        }

        try {
            return $this->buildLocked($zoneKey);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function buildLocked(string $zoneKey): array
    {
        [$scopeType, $scopeId] = $this->parseZoneKey($zoneKey);

        $zone = RpzZone::firstOrCreate(
            ['zone_key' => $zoneKey],
            [
                'scope_type' => $scopeType,
                'empresa_id' => $scopeType === 'company' ? $scopeId : null,
                'servidor_id' => $scopeType === 'server' ? $scopeId : null,
                'zone_name' => $this->builder->panelHost(),
                'last_serial' => 0,
                'revision' => 0,
                'content_hash' => '',
                'desired_generation' => 0,
                'built_generation' => 0,
            ]
        );

        // No-op barato: mesmo sem nenhuma query pesada, ja sabemos que nao ha
        // nada a fazer. E o que faz jobs duplicados na fila serem inofensivos.
        // Excecao: zona sem NENHUMA versao publicada ainda sempre tenta
        // construir (bootstrap) mesmo com desired_generation=built_generation
        // (=0 as duas, zona nunca vista por uma mutacao ainda).
        if ($zone->current_version_id !== null && ! $zone->needsRebuild()) {
            return ['status' => 'noop', 'zone_key' => $zoneKey];
        }

        $capturedGeneration = (int) DB::table('rpz_zones')->where('id', $zone->id)->value('desired_generation');
        $inicio = microtime(true);

        $resolved = $this->resolveScope($scopeType, $scopeId);
        if ($resolved === null) {
            // Servidor/empresa nao existe mais (ou inativo) -- nao ha o que
            // construir. Marca como reconciliado pra nao ficar reprocessando
            // pra sempre; se voltar a existir, uma nova mutacao vai marcar
            // dirty de novo.
            $zone->forceFill(['built_generation' => $capturedGeneration])->save();

            return ['status' => 'skipped_inactive', 'zone_key' => $zoneKey];
        }
        [$query, $target, $empresaId, $servidorId] = $resolved;

        AuditLog::record('rpz.build_started', "Build da zona RPZ iniciado ({$zoneKey})", $empresaId, 'rpz_zone', $zone->id);

        $host = $this->builder->panelHost();
        $canonical = $this->builder->buildCanonical($query, $host, $target);

        $conteudoJaPublicado = $zone->currentVersion !== null
            && $zone->currentVersion->content_hash === $canonical['content_hash'];

        if ($conteudoJaPublicado) {
            fclose($canonical['stream']);
            $zone->forceFill([
                'built_generation' => $capturedGeneration,
                'domain_count' => $canonical['domain_count'],
                'rule_count' => $canonical['rule_count'],
                'excluded_count' => $canonical['excluded_count'],
                'generated_at' => now(),
            ])->save();
            AuditLog::record('rpz.build_unchanged', "Conteúdo da zona RPZ inalterado ({$zoneKey})", $empresaId, 'rpz_zone', $zone->id);
            $this->reconcileIfStale($zone->id, $capturedGeneration);

            return ['status' => 'unchanged', 'zone_key' => $zoneKey];
        }

        // Reusa a decisao de serial/revision ja testada da fase anterior --
        // segura mesmo com jobs concorrentes (ver RpzZoneVersioner, retry +
        // WAL/busy_timeout). Serial/revision podem avancar mais de uma vez
        // em sequencia rapida se houver mudancas concorrentes; isso e valido
        // (RFC 1982 so exige nao regredir, nao exige avancar so 1x). O que
        // realmente precisa de protecao contra "stale" e o ARQUIVO publicado
        // (current_version_id), tratado abaixo.
        $zoneVersionada = $this->versioner->resolveVersion(
            $zoneKey,
            $scopeType,
            $empresaId,
            $servidorId,
            $host,
            $canonical['content_hash'],
            $canonical['domain_count'],
            $canonical['rule_count'],
            $canonical['excluded_count'],
            fn () => (int) $this->builder->computeSerial($query) % (RpzZoneVersioner::SERIAL_MAX + 1),
        );

        $candidato = RpzZoneVersion::create([
            'rpz_zone_id' => $zone->id,
            'revision' => $zoneVersionada->revision,
            'serial' => $zoneVersionada->last_serial,
            'content_hash' => $canonical['content_hash'],
            'domain_count' => $canonical['domain_count'],
            'rule_count' => $canonical['rule_count'],
            'excluded_count' => $canonical['excluded_count'],
            'generated_at' => now(),
            'status' => 'building',
            'previous_version_id' => $zone->current_version_id,
        ]);

        $stagingPath = "rpz/{$zone->id}/staging/{$candidato->id}.zone.tmp";
        Storage::disk('local')->makeDirectory("rpz/{$zone->id}/staging");
        Storage::disk('local')->makeDirectory("rpz/{$zone->id}/versions");

        $fileHashCtx = hash_init('sha256');
        $bytes = 0;
        $fh = fopen(Storage::disk('local')->path($stagingPath), 'w');
        foreach ($this->builder->renderZone($canonical, $host, (string) $zoneVersionada->last_serial, $target) as $bloco) {
            fwrite($fh, $bloco);
            hash_update($fileHashCtx, $bloco);
            $bytes += strlen($bloco);
        }
        fclose($fh);
        fclose($canonical['stream']);
        $fileHash = hash_final($fileHashCtx);

        $validacao = $this->validateArtifact(Storage::disk('local')->path($stagingPath), $bytes, $host, (string) $zoneVersionada->last_serial);
        if (! $validacao['ok']) {
            Storage::disk('local')->delete($stagingPath);
            $candidato->update(['status' => 'failed']);
            AuditLog::record('rpz.build_failed', "Falha ao validar artefato RPZ ({$zoneKey}): {$validacao['erro']}", $empresaId, 'rpz_zone', $zone->id);

            return ['status' => 'failed', 'zone_key' => $zoneKey];
        }

        $candidato->update([
            'file_hash' => $fileHash,
            'file_size' => $bytes,
            'validated_at' => now(),
            'status' => 'validated',
        ]);
        AuditLog::record('rpz.build_validated', "Artefato RPZ validado ({$zoneKey}, {$bytes} bytes)", $empresaId, 'rpz_zone', $zone->id);

        // STALE CHECK -- antes de publicar, confere se alguma mutacao chegou
        // DURANTE o build. Se sim, este candidato ja nasceu desatualizado:
        // descarta sem nunca vira-lo current_version_id, e agenda reconciliacao.
        $generationAtual = (int) DB::table('rpz_zones')->where('id', $zone->id)->value('desired_generation');
        if ($generationAtual !== $capturedGeneration) {
            Storage::disk('local')->delete($stagingPath);
            $candidato->update(['status' => 'failed']);
            AuditLog::record('rpz.build_failed', "Candidato descartado por ficar obsoleto durante o build ({$zoneKey})", $empresaId, 'rpz_zone', $zone->id);
            BuildRpzZone::dispatch($zoneKey);

            return ['status' => 'stale_discarded', 'zone_key' => $zoneKey];
        }

        $finalPath = "rpz/{$zone->id}/versions/{$candidato->id}.zone";
        $absStaging = Storage::disk('local')->path($stagingPath);
        $absFinal = Storage::disk('local')->path($finalPath);
        if (! rename($absStaging, $absFinal)) {
            $candidato->update(['status' => 'failed']);
            AuditLog::record('rpz.build_failed', "Falha ao publicar artefato RPZ (rename) ({$zoneKey})", $empresaId, 'rpz_zone', $zone->id);

            return ['status' => 'failed', 'zone_key' => $zoneKey];
        }
        chmod($absFinal, 0440); // artefato publicado e imutavel a partir daqui.

        DB::transaction(function () use ($zone, $candidato, $finalPath, $capturedGeneration): void {
            $anteriorId = $zone->current_version_id;
            $candidato->forceFill(['status' => 'published', 'published_at' => now(), 'file_path' => $finalPath])->save();
            if ($anteriorId !== null) {
                RpzZoneVersion::where('id', $anteriorId)->update(['status' => 'superseded']);
            }
            RpzZone::where('id', $zone->id)->update([
                'current_version_id' => $candidato->id,
                'built_generation' => $capturedGeneration,
                'generated_at' => now(),
            ]);
        });

        AuditLog::record(
            'rpz.version_published',
            "Nova versão RPZ publicada ({$zoneKey}, revision {$zoneVersionada->revision}, {$bytes} bytes, ".round((microtime(true) - $inicio) * 1000).'ms)',
            $empresaId,
            'rpz_zone',
            $zone->id,
        );

        $this->reconcileIfStale($zone->id, $capturedGeneration);

        return ['status' => 'published', 'zone_key' => $zoneKey, 'revision' => $zoneVersionada->revision, 'serial' => $zoneVersionada->last_serial];
    }

    /**
     * Se desired_generation avançou de novo enquanto publicávamos (nova
     * mutação chegou depois do stale-check mas antes do commit, ou durante o
     * unchanged-path), despacha mais um build pra pegar o estado mais
     * recente -- sem loop síncrono, só um novo job na fila.
     */
    private function reconcileIfStale(int $zoneId, int $builtGeneration): void
    {
        $atual = DB::table('rpz_zones')->where('id', $zoneId)->value('desired_generation');
        if ($atual !== null && (int) $atual > $builtGeneration) {
            $zoneKey = DB::table('rpz_zones')->where('id', $zoneId)->value('zone_key');
            if ($zoneKey !== null) {
                BuildRpzZone::dispatch($zoneKey);
            }
        }
    }

    /**
     * @return array{ok: bool, erro: ?string}
     */
    private function validateArtifact(string $path, int $bytes, string $zoneHost, string $serial): array
    {
        if ($bytes <= 0 || ! is_file($path)) {
            return ['ok' => false, 'erro' => 'arquivo vazio ou ausente'];
        }

        $inicio = fopen($path, 'r');
        $trecho = $inicio !== false ? fread($inicio, 4096) : '';
        if ($inicio !== false) {
            fclose($inicio);
        }

        if (! str_contains((string) $trecho, '$TTL')
            || ! str_contains((string) $trecho, '@ SOA '.$zoneHost)
            || ! str_contains((string) $trecho, $serial.'  ; serial')
            || ! str_contains((string) $trecho, 'NS localhost.')) {
            return ['ok' => false, 'erro' => 'cabecalho SOA/NS/serial nao encontrado no artefato'];
        }

        // named-checkzone e best-effort: se o binario nao existir no ambiente,
        // a validacao estrutural acima ja e suficiente -- nao torna a fase
        // dependente de um binario que sabidamente falta no container de
        // testes (ver RpzZonefileTest, falha ambiental ja documentada).
        // Argumentos fixos em array (nunca string interpolada em shell).
        $disponivel = Process::timeout(5)->run(['which', 'named-checkzone']);
        if ($disponivel->successful()) {
            $resultado = Process::timeout(15)->run(['named-checkzone', $zoneHost, $path]);
            if (! $resultado->successful()) {
                return ['ok' => false, 'erro' => 'named-checkzone: '.trim($resultado->errorOutput().' '.$resultado->output())];
            }
        }

        return ['ok' => true, 'erro' => null];
    }

    /** @return array{0: Builder, 1: string, 2: ?int, 3: ?int}|null */
    private function resolveScope(string $scopeType, int $scopeId): ?array
    {
        if ($scopeType === 'server') {
            $servidor = Servidor::where('id', $scopeId)->where('status', 'active')->first();
            if ($servidor === null) {
                return null;
            }

            return [
                $this->builder->serverQuery($servidor->id),
                $this->builder->target($servidor->bloqueio_modo),
                $servidor->empresa_id,
                $servidor->id,
            ];
        }

        $empresa = Empresa::where('id', $scopeId)->where('status', 'active')->first();
        if ($empresa === null || ! $empresa->possuiLicencaAtiva()) {
            return null;
        }

        return [$this->builder->companyQuery($empresa->id), '.', $empresa->id, null];
    }

    /** @return array{0: string, 1: int} */
    private function parseZoneKey(string $zoneKey): array
    {
        [$scopeType, $scopeId] = array_pad(explode(':', $zoneKey, 2), 2, null);
        if (! in_array($scopeType, ['server', 'company'], true) || ! ctype_digit((string) $scopeId)) {
            throw new \InvalidArgumentException("zone_key invalido: {$zoneKey}");
        }

        return [$scopeType, (int) $scopeId];
    }

    private function lockPath(string $zoneKey): string
    {
        return 'rpz/locks/'.str_replace(':', '_', $zoneKey).'.lock';
    }
}
