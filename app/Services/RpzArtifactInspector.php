<?php

namespace App\Services;

use App\Models\RpzZone;
use Illuminate\Support\Facades\Storage;

/** Read the published artifact; never rebuild, publish or contact a resolver. */
class RpzArtifactInspector
{
    public function inspect(RpzZone $zone): array
    {
        $result = ['zone' => $zone->zone_key, 'serial' => null, 'ok' => false,
            'actions' => ['nxdomain' => 0, 'passthru' => 0, 'redirect' => 0], 'errors' => []];
        $version = $zone->currentVersion;
        if ($version === null) {
            $result['errors'][] = 'Sem versão publicada.';

            return $result;
        }
        $result['serial'] = $version->serial;
        if ($version->rpz_zone_id !== $zone->id || $version->status !== 'published') {
            $result['errors'][] = 'Referência de versão publicada inválida.';

            return $result;
        }
        $expected = "rpz/{$zone->id}/versions/{$version->id}.zone";
        if ($version->file_path !== $expected) {
            $result['errors'][] = 'Caminho do artefato incompatível com a versão.';

            return $result;
        }
        $path = Storage::disk('local')->path($expected);
        if (! is_file($path) || ! is_readable($path)) {
            $result['errors'][] = 'Arquivo publicado ausente ou sem permissão de leitura.';

            return $result;
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            $result['errors'][] = 'Falha ao abrir o artefato publicado.';

            return $result;
        }
        $hash = hash_init('sha256');
        $bytes = 0;
        $rules = 0;
        $oversized = 0;
        $canary = false;
        $host = rtrim($zone->zone_name, '.');
        try {
            while (($line = fgets($stream)) !== false) {
                hash_update($hash, $line);
                $bytes += strlen($line);
                if (! preg_match('/^(\S+)\s+CNAME\s+(\S+)\s*$/i', trim($line), $match)) {
                    continue;
                }
                [$unused, $owner, $target] = $match;
                $rules++;
                $absolute = str_ends_with($owner, '.') ? rtrim($owner, '.') : $owner.'.'.$host;
                if (strlen($absolute) > 253 || max(array_map('strlen', explode('.', $absolute))) > 63) {
                    $oversized++;
                }
                if (strtolower($owner) === 'blocktest.'.strtolower($host) && $target === '.') {
                    $canary = true;
                    continue;
                }
                $action = $target === '.' ? 'nxdomain' : (strtolower($target) === 'rpz-passthru.' ? 'passthru' : 'redirect');
                $result['actions'][$action]++;
            }
            if (! feof($stream)) {
                $result['errors'][] = 'Leitura incompleta do artefato.';
            }
        } finally {
            fclose($stream);
        }
        if (! hash_equals((string) $version->file_hash, hash_final($hash))) {
            $result['errors'][] = 'SHA-256 diferente do arquivo publicado.';
        }
        if ($bytes !== $version->file_size) {
            $result['errors'][] = 'Tamanho diferente do arquivo publicado.';
        }
        // rule_count excludes the fixed canary emitted by renderZone().
        if ($rules !== $version->rule_count + 1) {
            $result['errors'][] = 'Quantidade de regras diferente da versão publicada.';
        }
        if (! $canary) {
            $result['errors'][] = 'Domínio de teste sem bloqueio NXDOMAIN.';
        }
        if ($oversized > 0) {
            $result['errors'][] = "{$oversized} regras excedem o limite de nomes DNS.";
        }
        $result['ok'] = $result['errors'] === [];

        return $result;
    }
}
