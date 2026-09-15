<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;

class R2BackupUploader
{
    public function config(): array
    {
        return [
            'ativo' => Setting::get('r2_ativo', '0') === '1',
            'account_id' => Setting::get('r2_account_id') ?: config('services.r2.account_id'),
            'access_key_id' => Setting::getEncrypted('r2_access_key_id') ?: config('services.r2.access_key_id'),
            'secret_access_key' => Setting::getEncrypted('r2_secret_access_key') ?: config('services.r2.secret_access_key'),
            'bucket' => Setting::get('r2_bucket') ?: config('services.r2.bucket'),
            'keep_days' => (int) (Setting::get('r2_keep_days') ?: config('services.r2.keep_days', 30)),
        ];
    }

    public function isConfigured(): bool
    {
        $config = $this->config();

        return $config['ativo']
            && filled($config['account_id'])
            && filled($config['access_key_id'])
            && filled($config['secret_access_key'])
            && filled($config['bucket']);
    }

    /**
     * Envia um arquivo local pro bucket R2. Retorna null em sucesso, ou uma
     * mensagem de erro curta (nunca a exceção crua, que pode conter a secret
     * key no texto de erro do SDK da AWS) em caso de falha.
     */
    public function upload(string $localPath, string $remoteName): ?string
    {
        if (! $this->isConfigured()) {
            return 'R2 não está configurado.';
        }

        if (! is_file($localPath)) {
            return 'Arquivo local não encontrado.';
        }

        try {
            $disk = $this->disk();
            $stream = fopen($localPath, 'r');
            if ($stream === false) {
                return 'Não foi possível abrir o arquivo local.';
            }

            try {
                $disk->writeStream('backups/'.$remoteName, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            return null;
        } catch (UnableToWriteFile|FilesystemException $e) {
            Log::warning('Falha ao enviar backup pro R2', ['erro' => 'Falha de comunicação com R2.']);

            return 'Falha ao enviar pro R2 — confira as credenciais e o nome do bucket.';
        } catch (\Throwable $e) {
            Log::warning('Exceção inesperada ao enviar backup pro R2', ['erro' => 'Falha inesperada.']);

            return 'Erro inesperado ao enviar pro R2.';
        }
    }

    /**
     * Apaga backups no R2 mais antigos que o prazo de retenção configurado
     * (padrão 30 dias). Só mexe em arquivos database.sqlite.auto-*.bak —
     * nunca no teste de conexão nem em qualquer outra coisa que alguém
     * tenha colocado na pasta backups/ manualmente.
     *
     * @return array{removidos: int, erro: ?string}
     */
    public function pruneOldBackups(): array
    {
        if (! $this->isConfigured()) {
            return ['removidos' => 0, 'erro' => 'R2 não está configurado.'];
        }

        $config = $this->config();
        $limite = now()->subDays($config['keep_days'])->getTimestamp();

        try {
            $disk = $this->disk();
            $removidos = 0;

            foreach ($disk->listContents('backups') as $item) {
                if ($item->isDir()) {
                    continue;
                }

                $nome = basename($item->path());

                if (! str_starts_with($nome, 'database.sqlite.auto-') || ! str_ends_with($nome, '.bak')) {
                    continue;
                }

                if ($item->lastModified() !== null && $item->lastModified() < $limite) {
                    $disk->delete($item->path());
                    $removidos++;
                }
            }

            return ['removidos' => $removidos, 'erro' => null];
        } catch (\Throwable $e) {
            Log::warning('Falha ao limpar backups antigos no R2', ['erro' => 'Falha de comunicação com R2.']);

            return ['removidos' => 0, 'erro' => 'Falha ao limpar backups antigos no R2.'];
        }
    }

    public function sendTest(): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'r2-teste-');
        file_put_contents($tmp, 'teste de conexao do DNS Panel RPZ em '.now()->toDateTimeString());

        try {
            return $this->upload($tmp, 'teste-conexao.txt');
        } finally {
            @unlink($tmp);
        }
    }

    private function disk()
    {
        $config = $this->config();

        return Storage::build([
            'driver' => 's3',
            'key' => $config['access_key_id'],
            'secret' => $config['secret_access_key'],
            'region' => 'auto',
            'bucket' => $config['bucket'],
            'endpoint' => "https://{$config['account_id']}.r2.cloudflarestorage.com",
            'use_path_style_endpoint' => true,
        ]);
    }
}
