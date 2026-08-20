<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Dominio;
use App\Models\Lista;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncUrlhausFeed extends Command
{
    protected $signature = 'urlhaus:sync';

    protected $description = 'Sincroniza a lista externa de domínios maliciosos do feed público URLhaus (abuse.ch)';

    private const FEED_URL = 'https://urlhaus.abuse.ch/downloads/hostfile/';

    private const MIN_DOMAINS_ESPERADOS = 100;

    private const CHUNK_SIZE = 400;

    public function handle(): int
    {
        $lista = Lista::firstOrCreate(
            ['fonte_externa' => 'urlhaus'],
            [
                'empresa_id' => null,
                'nome' => 'URLhaus — Malware & Phishing (auto)',
                'descricao' => 'Feed público de domínios de malware/phishing (abuse.ch). Sincronizado automaticamente — não edite manualmente.',
                'status' => 'active',
                'origem' => 'externa',
                'sync_ativo' => true,
            ]
        );

        if (! $lista->sync_ativo) {
            $this->info('Sincronização pausada para esta lista (sync_ativo = false). Nada a fazer.');

            return self::SUCCESS;
        }

        try {
            $response = Http::timeout(30)->get(self::FEED_URL);
        } catch (\Throwable $e) {
            Log::warning('urlhaus:sync falhou ao buscar o feed: ' . $e->getMessage());
            $this->error('Falha ao buscar o feed: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            Log::warning('urlhaus:sync recebeu status HTTP ' . $response->status());
            $this->error('Feed respondeu com status ' . $response->status());

            return self::FAILURE;
        }

        $dominios = $this->parseHostfile($response->body());

        if (count($dominios) < self::MIN_DOMAINS_ESPERADOS) {
            Log::warning('urlhaus:sync abortado: feed retornou apenas ' . count($dominios) . ' domínios (mínimo esperado ' . self::MIN_DOMAINS_ESPERADOS . '). Possível falha de formato ou feed fora do ar — lista não foi alterada.');
            $this->error('Feed retornou poucos domínios (' . count($dominios) . ') — abortando para não esvaziar a lista por engano.');

            return self::FAILURE;
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

        $removidos = count($paraDesativar);
        $total = count($dominios);

        AuditLog::record(
            'lista.externa.sync',
            "Lista externa \"{$lista->nome}\" sincronizada: {$total} ativos no feed, {$adicionados} novos, {$removidos} desativados",
            null,
            'lista',
            $lista->id
        );

        $this->info("OK: {$total} domínios ativos, {$adicionados} novos, {$removidos} desativados.");

        return self::SUCCESS;
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

            $dominio = strtolower(trim($parts[1]));
            $dominio = rtrim($dominio, '.');

            if ($dominio === '' || $dominio === 'localhost') {
                continue;
            }

            if (! preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.[a-z0-9-]{1,63})*\.[a-z]{2,63}$/', $dominio)) {
                continue;
            }

            $dominios[$dominio] = true;
        }

        return array_keys($dominios);
    }
}
