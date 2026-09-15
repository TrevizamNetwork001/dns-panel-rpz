<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    public function config(): array
    {
        return [
            'ativo' => Setting::get('telegram_ativo', '1') === '1',
            'bot_token' => Setting::getEncrypted('telegram_bot_token') ?: config('services.telegram.bot_token'),
            'chat_id' => Setting::get('telegram_chat_id') ?: config('services.telegram.cadastros_chat_id'),
            'thread_id' => Setting::get('telegram_thread_id') ?: config('services.telegram.cadastros_thread_id'),
        ];
    }

    public function notifyCadastro(string $empresaNome, string $responsavelNome, string $email): bool
    {
        $texto = "🆕 <b>Novo cadastro no painel RPZ</b>\n\n"
            .'<b>Empresa:</b> '.e($empresaNome)."\n"
            .'<b>Responsável:</b> '.e($responsavelNome)."\n"
            .'<b>E-mail:</b> '.e($email)."\n\n"
            .'Status: aguardando aprovação.';

        return $this->send($texto);
    }

    public function sendTest(): bool
    {
        return $this->send('✅ Teste de conexão do painel RPZ. Se você está vendo isso, a integração funciona.');
    }

    /**
     * @param  array<int, array{action: string, description: string}>  $problemas
     */
    public function notifyHealthProblems(array $problemas): bool
    {
        if ($problemas === []) {
            return false;
        }

        $itens = array_map(
            fn (array $problema) => '• '.e($problema['description']),
            $problemas
        );

        return $this->send("🚨 <b>Alerta de saúde do DNS Panel RPZ</b>\n\n".implode("\n", $itens));
    }

    public function notifyHealthRecovered(): bool
    {
        return $this->send('✅ <b>DNS Panel RPZ recuperado</b>'."\n\n".'O healthcheck voltou a passar: disco, certificado e site OK.');
    }

    public function notifyRbl(string $texto): bool
    {
        return $this->send($texto);
    }

    private function send(string $texto): bool
    {
        $config = $this->config();

        if (! $config['ativo'] || ! $config['bot_token'] || ! $config['chat_id']) {
            return false;
        }

        $payload = [
            'chat_id' => $config['chat_id'],
            'text' => $texto,
            'parse_mode' => 'HTML',
        ];

        if ($config['thread_id']) {
            $payload['message_thread_id'] = $config['thread_id'];
        }

        try {
            $response = Http::timeout(5)->post("https://api.telegram.org/bot{$config['bot_token']}/sendMessage", $payload);

            if (! $response->successful() || $response->json('ok') !== true) {
                Log::warning('Falha ao enviar notificação Telegram', ['status' => $response->status()]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Exceção ao enviar notificação Telegram', ['erro' => 'Falha de comunicação com Telegram.']);

            return false;
        }
    }
}
