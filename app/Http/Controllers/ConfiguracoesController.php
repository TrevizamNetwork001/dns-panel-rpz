<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthenticateRpzOperations;
use App\Jobs\RunDatabaseBackup;
use App\Models\AuditLog;
use App\Models\Lista;
use App\Models\Setting;
use App\Services\R2BackupUploader;
use App\Services\ServidoresInativosCleaner;
use App\Services\TelegramNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ConfiguracoesController extends Controller
{
    public function index(Request $request, TelegramNotifier $telegram, R2BackupUploader $r2, ServidoresInativosCleaner $limpeza): Response
    {
        $fontesGerenciadas = Lista::query()
            ->whereIn('origem', ['externa', 'anatel'])
            ->get(['origem', 'sync_ativo', 'last_sync_at']);

        $ultimoBackup = AuditLog::where('action', 'like', 'backup.%')
            ->orderByDesc('id')
            ->first();

        $hermesHash = Setting::get(AuthenticateRpzOperations::TOKEN_HASH_KEY);
        $encryptedToken = $request->session()->pull('rpz_operations_new_token');
        $token = $encryptedToken === null ? null : Crypt::decryptString($encryptedToken);
        if ($token !== null && $hermesHash !== hash('sha256', $token)) {
            $token = null;
        }

        return response()->view('configuracoes.index', [
            'hermesConfigured' => $hermesHash !== null,
            'hermesToken' => $token,
            'telegram' => $telegram->config(),
            'imap' => [
                'email' => Setting::get('anatel_imap_email', 'rpz@trevizamnetwork.com.br'),
                'host' => Setting::get('anatel_imap_host', 'mail.trevizamnetwork.com.br'),
                'port' => Setting::get('anatel_imap_port', '993'),
                'has_password' => Setting::getEncrypted('anatel_imap_password') !== null,
            ],
            'r2' => $r2->config(),
            'r2Usage' => $r2->cachedUsage(),
            'limpeza' => $limpeza->config() + ['candidatos' => $limpeza->inativosQuery()->count()],
            'ultimoBackup' => $ultimoBackup,
            'geral' => [
                'nome' => config('app.name'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'ambiente' => app()->environment(),
            ],
            'integracoes' => [
                'total' => $fontesGerenciadas->count(),
                'ativas' => $fontesGerenciadas->where('sync_ativo', true)->count(),
                'pausadas' => $fontesGerenciadas->where('sync_ativo', false)->count(),
                'ultima_sync' => $fontesGerenciadas->max('last_sync_at'),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function generateHermesCredential(): RedirectResponse
    {
        $token = 'rpzo_'.bin2hex(random_bytes(32));

        DB::transaction(function () use ($token): void {
            Setting::set(AuthenticateRpzOperations::TOKEN_HASH_KEY, hash('sha256', $token));
            AuditLog::record('auth.rpz_operations_credential_generated', 'Credencial administrativa do Hermes gerada');
        });

        return redirect()->route('configuracoes.index', ['aba' => 'integracoes'])
            ->with('rpz_operations_new_token', Crypt::encryptString($token));
    }

    public function revokeHermesCredential(Request $request): RedirectResponse
    {
        DB::transaction(function (): void {
            Setting::set(AuthenticateRpzOperations::TOKEN_HASH_KEY, null);
            AuditLog::record('auth.rpz_operations_credential_revoked', 'Credencial administrativa do Hermes revogada');
        });

        $request->session()->forget('rpz_operations_new_token');

        return redirect()->route('configuracoes.index', ['aba' => 'integracoes'])
            ->with('status', 'Credencial do Hermes revogada.');
    }

    public function updateTelegram(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ativo' => ['nullable', 'boolean'],
            'bot_token' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:64'],
            'thread_id' => ['nullable', 'string', 'max:64'],
        ]);

        Setting::set('telegram_ativo', $request->boolean('ativo') ? '1' : '0');

        if (filled($data['bot_token'] ?? null)) {
            Setting::setEncrypted('telegram_bot_token', $data['bot_token']);
        }

        Setting::set('telegram_chat_id', $data['chat_id'] ?? null);
        Setting::set('telegram_thread_id', $data['thread_id'] ?? null);

        AuditLog::record('configuracoes.telegram_atualizado', 'Configuração de notificação Telegram atualizada');

        return back()->with('status', 'Configuração salva.');
    }

    public function updateImap(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('imap', [
            'email' => ['required', 'email:rfc', 'max:254'],
            'host' => ['required', 'string', 'max:253', 'regex:/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?\z/i'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'password' => ['nullable', 'string', 'max:1024'],
        ]);

        DB::transaction(function () use ($data): void {
            $port = (string) (int) $data['port'];
            $connectionChanged = $data['email'] !== Setting::get('anatel_imap_email')
                || $data['host'] !== Setting::get('anatel_imap_host')
                || $port !== Setting::get('anatel_imap_port');

            if (! isset($data['password']) && ($connectionChanged || Setting::getEncrypted('anatel_imap_password') === null)) {
                throw ValidationException::withMessages([
                    'password' => 'Informe a senha da caixa para salvar esta conexão.',
                ])->errorBag('imap');
            }

            Setting::set('anatel_imap_email', $data['email']);
            Setting::set('anatel_imap_host', $data['host']);
            Setting::set('anatel_imap_port', $port);

            if (isset($data['password'])) {
                Setting::setEncrypted('anatel_imap_password', $data['password']);
            }

            AuditLog::record('configuracoes.imap_atualizado', 'Configuração da caixa de entrada IMAP atualizada');
        });

        return redirect()->route('configuracoes.index', ['aba' => 'integracoes'])
            ->with('status', 'Configuração IMAP salva.');
    }

    public function testTelegram(TelegramNotifier $telegram): RedirectResponse
    {
        $ok = $telegram->sendTest();

        return back()->with($ok ? 'status' : 'error', $ok
            ? 'Mensagem enviada com sucesso.'
            : 'Falha ao enviar mensagem. Confira token, chat ID e se a notificação está ativa.');
    }

    public function updateR2(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ativo' => ['nullable', 'boolean'],
            'account_id' => ['nullable', 'string', 'max:255'],
            'access_key_id' => ['nullable', 'string', 'max:255'],
            'secret_access_key' => ['nullable', 'string', 'max:255'],
            'bucket' => ['nullable', 'string', 'max:255'],
            'keep_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        Setting::set('r2_ativo', $request->boolean('ativo') ? '1' : '0');
        Setting::set('r2_account_id', $data['account_id'] ?? null);
        Setting::set('r2_bucket', $data['bucket'] ?? null);
        Setting::set('r2_keep_days', $data['keep_days'] ?? null);

        if (filled($data['access_key_id'] ?? null)) {
            Setting::setEncrypted('r2_access_key_id', $data['access_key_id']);
        }

        if (filled($data['secret_access_key'] ?? null)) {
            Setting::setEncrypted('r2_secret_access_key', $data['secret_access_key']);
        }

        AuditLog::record('configuracoes.r2_atualizado', 'Configuração de backup externo (R2) atualizada');

        return back()->with('status', 'Configuração salva.');
    }

    public function updateLimpezaServidores(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ativo' => ['nullable', 'boolean'],
            'dias' => ['required', 'integer', 'min:30', 'max:365'],
            'aviso_dias' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        Setting::set('servidor_limpeza_ativo', $request->boolean('ativo') ? '1' : '0');
        Setting::set('servidor_limpeza_dias', (string) $data['dias']);
        Setting::set('servidor_limpeza_aviso_dias', (string) $data['aviso_dias']);

        AuditLog::record('configuracoes.limpeza_servidores_atualizado', 'Configuração de limpeza de servidores inativos atualizada');

        return back()->with('status', 'Configuração salva.');
    }

    public function testR2(R2BackupUploader $r2): RedirectResponse
    {
        $erro = $r2->sendTest();

        return back()->with($erro === null ? 'status' : 'error', $erro === null
            ? 'Comunicação com o R2 OK — escrita, leitura e exclusão confirmadas. Arquivo de teste removido.'
            : $erro);
    }

    public function r2Storage(R2BackupUploader $r2): RedirectResponse
    {
        $usage = $r2->storageUsage();

        return redirect()->to(route('configuracoes.index', ['aba' => 'backup']))
            ->with($usage['erro'] === null ? 'r2_usage' : 'error', $usage['erro'] ?? $usage);
    }

    public function runBackupNow(): RedirectResponse
    {
        RunDatabaseBackup::dispatch();
        AuditLog::record('backup.manual_triggered', 'Backup manual disparado pela tela de Configurações');

        return back()->with('status', 'Backup enfileirado. Acompanhe a conclusão nos eventos de backup e na auditoria.');
    }

}
