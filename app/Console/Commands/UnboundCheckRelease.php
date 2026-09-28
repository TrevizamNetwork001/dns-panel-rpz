<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnboundCheckRelease extends Command
{
    protected $signature = 'unbound:check-release';

    protected $description = 'Verifica se saiu release novo do Unbound no GitHub e avisa se o changelog menciona CVE (não atualiza a página de segurança sozinho)';

    private const RELEASES_URL = 'https://api.github.com/repos/NLnetLabs/unbound/releases';

    private const SETTING_KEY = 'unbound_last_checked_release';

    public function handle(TelegramNotifier $telegram): int
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'dns-panel-rpz',
            ])->timeout(15)->get(self::RELEASES_URL, ['per_page' => 1]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao consultar releases do Unbound no GitHub', ['erro' => $e->getMessage()]);
            $this->error('Falha ao consultar a API do GitHub.');

            return self::FAILURE;
        }

        if (! $response->successful() || empty($response->json())) {
            Log::warning('Resposta inesperada da API de releases do Unbound', ['status' => $response->status()]);
            $this->error('Resposta inesperada da API do GitHub (status '.$response->status().').');

            return self::FAILURE;
        }

        $release = $response->json()[0];
        $tag = (string) ($release['tag_name'] ?? '');
        $corpo = (string) ($release['body'] ?? '');
        $url = (string) ($release['html_url'] ?? 'https://github.com/NLnetLabs/unbound/releases');

        if ($tag === '') {
            $this->error('Release sem tag_name na resposta da API.');

            return self::FAILURE;
        }

        $ultimoConhecido = Setting::get(self::SETTING_KEY);

        if ($tag === $ultimoConhecido) {
            $this->info("Nenhum release novo desde \"{$tag}\".");

            return self::SUCCESS;
        }

        $mencionaCve = str_contains(mb_strtoupper($corpo), 'CVE');

        if ($mencionaCve) {
            $qtdCves = preg_match_all('/CVE-\d{4}-\d+/i', $corpo);
            $descricao = "Release \"{$tag}\" do Unbound saiu e o changelog menciona CVE (aprox. {$qtdCves} identificador(es)). Revise e atualize manualmente a página /unbound-seguranca se aplicável.";

            AuditLog::create([
                'user_id' => null,
                'empresa_id' => null,
                'action' => 'unbound.release.security',
                'description' => $descricao,
                'ip_address' => null,
                'created_at' => now(),
            ]);

            $telegram->notifyUnboundReleaseComCve($tag, $url, $qtdCves);

            $this->info("Release novo com possível CVE: {$tag}. Auditoria e Telegram avisados.");
        } else {
            $this->info("Release novo sem menção a CVE no changelog: {$tag}. Nenhum aviso disparado.");
        }

        Setting::set(self::SETTING_KEY, $tag);

        return self::SUCCESS;
    }
}
