<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\TelegramNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfiguracoesController extends Controller
{
    public function index(TelegramNotifier $telegram): View
    {
        return view('configuracoes.index', ['telegram' => $telegram->config()]);
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
            ? 'Mensagem de teste enviada — confira o grupo/tópico configurado.'
            : 'Não consegui enviar. Confira token, chat ID e se a notificação está ativa.');
    }
}
