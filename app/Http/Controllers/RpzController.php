<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\RpzZone;
use App\Models\RpzZoneVersion;
use App\Models\ServerSyncLog;
use App\Models\Servidor;
use App\Services\RpzZoneBuilder;
use App\Services\RpzZoneVersioner;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RpzController extends Controller
{
    /**
     * Gera o zonefile RPZ do servidor identificado pelo token.
     */
    public function show(Request $request, string $identifier, RpzZoneBuilder $builder, RpzZoneVersioner $versioner): Response|StreamedResponse|BinaryFileResponse
    {
        $empresa = Empresa::where('rpz_slug', $identifier)->where('status', 'active')->first();
        if ($empresa !== null) {
            return $this->showCompany($request, $empresa, $builder, $versioner);
        }

        $servidor = Servidor::where('token', $identifier)
            ->where('status', 'active')
            ->whereHas('empresa', function ($query) {
                $query->where('status', 'active');
            })
            ->with('empresa')
            ->firstOrFail();

        if (! $servidor->ipAllowed($request->ip())) {
            throw new NotFoundHttpException;
        }

        if (! $servidor->empresa->possuiLicencaAtiva()) {
            throw new NotFoundHttpException;
        }

        $inicio = microtime(true);
        $requestId = (string) Str::uuid();
        $userAgent = substr((string) $request->userAgent(), 0, 190) ?: null;
        $servidor->forceFill(['last_synced_at' => now(), 'last_user_agent' => $userAgent])->saveQuietly();

        $zoneKeyEstatico = 'server:'.$servidor->id;
        $estatico = $this->serveStaticArtifact($zoneKeyEstatico, $requestId);
        if ($estatico !== null) {
            ServerSyncLog::create([
                'servidor_id' => $servidor->id,
                'ip_address' => $request->ip(),
                'user_agent' => $userAgent,
                'dominios_count' => $estatico['version']->domain_count,
                'request_id' => $requestId,
                'zone_key' => $zoneKeyEstatico,
                'revision' => $estatico['version']->revision,
                'content_hash' => $estatico['version']->content_hash,
                'rule_count' => $estatico['version']->rule_count,
                'excluded_count' => $estatico['version']->excluded_count,
                'duration_ms' => (int) round((microtime(true) - $inicio) * 1000),
                'created_at' => now(),
            ]);

            return $estatico['response'];
        }

        $host = $builder->panelHost();
        $target = $builder->target($servidor->bloqueio_modo);
        // Consulta enxuta de proposito: com listas grandes (feeds de threat intel
        // chegam a dezenas de milhares de dominios), carregar tudo como models
        // Eloquent (via with()/pluck()/flatten() em memoria) estoura o
        // memory_limit do PHP-FPM. Aqui so trafega a coluna que interessa.
        $dominios = $builder->serverQuery($servidor->id);
        $zoneKey = $versioner->zoneKey('server', $servidor->id);
        $refresh = $builder->refreshFor($zoneKey);
        $canonical = $builder->buildCanonical($dominios, $host, $target, $refresh);
        $contentHash = $canonical['content_hash'];
        $zona = $versioner->resolveVersion(
            $zoneKey,
            'server',
            $servidor->empresa_id,
            $servidor->id,
            $host,
            $contentHash,
            $canonical['domain_count'],
            $canonical['rule_count'],
            $canonical['excluded_count'],
            // Bootstrap unico: reusa o CRC32b legado (nova consulta, so na primeira
            // vez que essa zona logica e vista) pra nao regredir o serial ja
            // distribuido aos resolvers antes deste versionamento existir.
            fn () => (int) $builder->computeSerial($dominios) % (RpzZoneVersioner::SERIAL_MAX + 1),
        );

        ServerSyncLog::create([
            'servidor_id' => $servidor->id,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'dominios_count' => $canonical['domain_count'],
            'request_id' => $requestId,
            'zone_key' => $zoneKey,
            'revision' => $zona->revision,
            'content_hash' => $contentHash,
            'rule_count' => $canonical['rule_count'],
            'excluded_count' => $canonical['excluded_count'],
            'duration_ms' => (int) round((microtime(true) - $inicio) * 1000),
            'created_at' => now(),
        ]);

        try {
            $zone = implode('', iterator_to_array($builder->renderZone($canonical, $host, (string) $zona->last_serial, $target, $refresh), false));
        } finally {
            fclose($canonical['stream']);
        }

        return response($zone, 200, [
            'Content-Type' => 'text/dns; charset=utf-8',
            'X-Rpz-Request-Id' => $requestId,
            'X-Rpz-Zone-Key' => $zoneKey,
            'X-Rpz-Serial' => (string) $zona->last_serial,
            'X-Rpz-Revision' => (string) $zona->revision,
            'X-Rpz-Domain-Count' => (string) $canonical['domain_count'],
            'X-Rpz-Rule-Count' => (string) $canonical['rule_count'],
            'X-Rpz-Content-Hash' => $contentHash,
        ]);
    }

    private function showCompany(Request $request, Empresa $empresa, RpzZoneBuilder $builder, RpzZoneVersioner $versioner): Response|StreamedResponse|BinaryFileResponse
    {
        if (! $empresa->possuiLicencaAtiva()) {
            throw new NotFoundHttpException;
        }

        $ip = (string) $request->ip();
        $matchingServers = Servidor::query()
            ->where('empresa_id', $empresa->id)
            ->where('status', 'active')
            ->whereHas('allowedIps', fn ($query) => $query->where('status', 'active'))
            ->with(['allowedIps' => fn ($query) => $query->where('status', 'active')])
            ->get()
            ->filter(fn (Servidor $servidor): bool => $servidor->allowedIps
                ->contains(fn ($rule): bool => Servidor::ipMatchesCidr($ip, $rule->ip_cidr)));

        if ($matchingServers->isEmpty()) {
            AuditLog::record('rpz.endpoint.denied', 'Acesso ao endpoint RPZ empresarial negado', $empresa->id, 'empresa', $empresa->id);

            return response()->view('rpz.access-denied', [], 403, [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $inicio = microtime(true);
        $requestId = (string) Str::uuid();
        $userAgent = substr((string) $request->userAgent(), 0, 190) ?: null;

        $zoneKeyEstatico = 'company:'.$empresa->id;
        $estatico = $this->serveStaticArtifact($zoneKeyEstatico, $requestId);
        if ($estatico !== null) {
            foreach ($matchingServers as $servidor) {
                $servidor->forceFill(['last_synced_at' => now(), 'last_user_agent' => $userAgent])->saveQuietly();
                ServerSyncLog::create([
                    'servidor_id' => $servidor->id,
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'dominios_count' => $estatico['version']->domain_count,
                    'request_id' => $requestId,
                    'zone_key' => $zoneKeyEstatico,
                    'revision' => $estatico['version']->revision,
                    'content_hash' => $estatico['version']->content_hash,
                    'rule_count' => $estatico['version']->rule_count,
                    'excluded_count' => $estatico['version']->excluded_count,
                    'duration_ms' => (int) round((microtime(true) - $inicio) * 1000),
                    'created_at' => now(),
                ]);
            }
            AuditLog::record('rpz.endpoint.downloaded', "Endpoint RPZ empresarial solicitado ({$estatico['version']->domain_count} domínios, revision {$estatico['version']->revision}, artefato estático)", $empresa->id, 'empresa', $empresa->id);

            return $estatico['response'];
        }

        $host = $builder->panelHost();
        $target = '.';
        $domains = $builder->companyQuery($empresa->id);
        $canonical = $builder->buildCanonical($domains, $host, $target);
        $contentHash = $canonical['content_hash'];

        $zoneKey = $versioner->zoneKey('company', $empresa->id);
        $zona = $versioner->resolveVersion(
            $zoneKey,
            'company',
            $empresa->id,
            null,
            $host,
            $contentHash,
            $canonical['domain_count'],
            $canonical['rule_count'],
            $canonical['excluded_count'],
            fn () => (int) $builder->computeSerial($domains) % (RpzZoneVersioner::SERIAL_MAX + 1),
        );

        foreach ($matchingServers as $servidor) {
            $servidor->forceFill(['last_synced_at' => now(), 'last_user_agent' => $userAgent])->saveQuietly();
            ServerSyncLog::create([
                'servidor_id' => $servidor->id,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'dominios_count' => $canonical['domain_count'],
                'request_id' => $requestId,
                'zone_key' => $zoneKey,
                'revision' => $zona->revision,
                'content_hash' => $contentHash,
                'rule_count' => $canonical['rule_count'],
                'excluded_count' => $canonical['excluded_count'],
                'duration_ms' => (int) round((microtime(true) - $inicio) * 1000),
                'created_at' => now(),
            ]);
        }

        // "Baixado" descreve a resposta HTTP concluida pelo painel, nao a
        // aplicacao no Unbound do cliente -- o painel nao tem como observar
        // isso (auditoria 29/09/2026, secao 9).
        AuditLog::record('rpz.endpoint.downloaded', "Endpoint RPZ empresarial solicitado ({$canonical['domain_count']} domínios, revision {$zona->revision})", $empresa->id, 'empresa', $empresa->id);

        // buildCanonical() grava as regras num stream temporario (nunca um array
        // PHP de centenas de milhares de elementos -- ver comentario no metodo).
        // A entrega HTTP le esse stream em blocos e transmite direto, sem
        // concatenar tudo numa string gigante, preservando streaming real.
        return response()->stream(function () use ($builder, $canonical, $host, $zona, $target): void {
            try {
                foreach ($builder->renderZone($canonical, $host, (string) $zona->last_serial, $target) as $bloco) {
                    echo $bloco;
                }
            } finally {
                fclose($canonical['stream']);
            }
        }, 200, [
            'Content-Type' => 'text/dns; charset=utf-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Rpz-Request-Id' => $requestId,
            'X-Rpz-Zone-Key' => $zoneKey,
            'X-Rpz-Serial' => (string) $zona->last_serial,
            'X-Rpz-Revision' => (string) $zona->revision,
            'X-Rpz-Domain-Count' => (string) $canonical['domain_count'],
            'X-Rpz-Rule-Count' => (string) $canonical['rule_count'],
            'X-Rpz-Content-Hash' => $contentHash,
        ]);
    }

    /**
     * Caminho estático (RPZ-BUILD-1): quando habilitado E existe versão
     * publicada válida pra essa zona, serve o arquivo pronto sem tocar a
     * tabela dominios. Retorna null em qualquer situação que deva cair no
     * caminho dinâmico de sempre (flag desligada, zona nunca construída,
     * arquivo ausente do disco) -- fallback seguro, nunca serve arquivo
     * parcial nem inventa conteúdo vazio.
     *
     * @return array{response: Response, version: RpzZoneVersion}|null
     */
    private function serveStaticArtifact(string $zoneKey, string $requestId): ?array
    {
        if (! config('rpz.static_artifacts_enabled')) {
            return null;
        }

        $zone = RpzZone::where('zone_key', $zoneKey)->with('currentVersion')->first();
        $version = $zone?->currentVersion;
        if ($version === null || $version->file_path === null) {
            return null;
        }

        $absPath = Storage::disk('local')->path($version->file_path);
        if (! is_file($absPath)) {
            return null;
        }

        $response = response()->file($absPath, [
            'Content-Type' => 'text/dns; charset=utf-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Rpz-Request-Id' => $requestId,
            'X-Rpz-Zone-Key' => $zoneKey,
            'X-Rpz-Serial' => (string) $version->serial,
            'X-Rpz-Revision' => (string) $version->revision,
            'X-Rpz-Domain-Count' => (string) $version->domain_count,
            'X-Rpz-Rule-Count' => (string) $version->rule_count,
            'X-Rpz-Content-Hash' => $version->content_hash,
            'X-Rpz-File-Hash' => (string) $version->file_hash,
        ]);

        return ['response' => $response, 'version' => $version];
    }
}
