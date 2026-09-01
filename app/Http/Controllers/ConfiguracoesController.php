<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lista;
use App\Models\Setting;
use App\Services\TelegramNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfiguracoesController extends Controller
{
    public function index(TelegramNotifier $telegram): View
    {
        $fontesGerenciadas = Lista::query()
            ->whereIn('origem', ['externa', 'anatel'])
            ->get(['origem', 'sync_ativo', 'last_sync_at']);

        return view('configuracoes.index', [
            'telegram' => $telegram->config(),
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
            Setting::set('telegram_bot_token', $data['bot_token']);
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
}
