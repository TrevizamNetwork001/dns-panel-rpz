<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lista;
use App\Models\Setting;
use App\Services\R2BackupUploader;
use App\Services\TelegramNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class ConfiguracoesController extends Controller
{
    public function index(TelegramNotifier $telegram, R2BackupUploader $r2): View
    {
        $fontesGerenciadas = Lista::query()
            ->whereIn('origem', ['externa', 'anatel'])
            ->get(['origem', 'sync_ativo', 'last_sync_at']);

        $ultimoBackup = AuditLog::where('action', 'like', 'backup.%')
            ->orderByDesc('id')
            ->first();

        return view('configuracoes.index', [
            'telegram' => $telegram->config(),
            'r2' => $r2->config(),
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
        ]);
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

    public function testR2(R2BackupUploader $r2): RedirectResponse
    {
        $erro = $r2->sendTest();

        return back()->with($erro === null ? 'status' : 'error', $erro === null
            ? 'Conexão com o R2 funcionando — arquivo de teste enviado.'
            : $erro);
    }

    public function runBackupNow(): RedirectResponse
    {
        Artisan::call('backup:run');
        $saida = trim(Artisan::output());

        AuditLog::record('backup.manual_triggered', 'Backup manual disparado pela tela de Configurações');

        return back()->with('status', 'Backup executado. '.$saida);
    }
}
