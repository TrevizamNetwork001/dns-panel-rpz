<?php

namespace App\Services\Rbl;

use App\Models\RblAlert;
use App\Models\RblEvent;
use App\Services\TelegramNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

class RblAlertService
{
    public array $counts = ['listed' => 0, 'resolved' => 0, 'sent' => 0, 'failed' => 0];

    public function __construct(private TelegramNotifier $telegram) {}

    public function resetCounts(): void
    {
        $this->counts = array_fill_keys(array_keys($this->counts), 0);
    }

    public function notify(RblEvent $event, string $type): void
    {
        if (! in_array($type, ['listed', 'resolved'], true)) {
            return;
        }
        $this->counts[$type]++;
        $alert = null;
        try {
            // Atomic reservation: even concurrent callers cannot send the same transition twice.
            $alert = RblAlert::firstOrCreate(
                ['rbl_event_id' => $event->id, 'type' => $type, 'channel' => 'telegram'],
                ['status' => 'skipped', 'error_message' => 'Envio não concluído; sem repetição automática.']
            );
            if (! $alert->wasRecentlyCreated) {
                return;
            }
            if (! config('rbl.alerts_enabled') || ! config('rbl.alert_on_'.$type)
                || ! in_array('telegram', config('rbl.alert_channels', []), true)) {
                $alert->update(['error_message' => 'Alerta desativado pela configuração RBL.']);

                return;
            }
            $settings = $this->telegram->config();
            if (! $settings['ativo'] || ! $settings['bot_token'] || ! $settings['chat_id']) {
                $alert->update(['error_message' => 'Telegram desativado ou não configurado.']);

                return;
            }
            $message = $this->message($event, $type);
            // Store no destination or message body: administrative settings remain the source of truth.
            $alert->update(['message_hash' => hash('sha256', $message)]);
            $sent = $this->telegram->notifyRbl($message);
            $alert->update([
                'status' => $sent ? 'sent' : 'failed', 'sent_at' => $sent ? now() : null,
                'error_message' => $sent ? null : 'Falha no envio Telegram. Verifique a configuração e a conectividade.',
            ]);
            $this->counts[$sent ? 'sent' : 'failed']++;
        } catch (Throwable) {
            $this->counts['failed']++;
            try {
                $alert?->update(['status' => 'failed', 'error_message' => 'Falha controlada ao processar alerta Telegram.']);
            } catch (Throwable) {
                // An unavailable audit store must not roll back the DNS checks.
            }
            Log::warning('Falha controlada no alerta RBL.');
        }
    }

    private function message(RblEvent $event, string $type): string
    {
        $event->loadMissing(['target.group', 'list']);
        $safe = fn ($value) => e(mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $value) ?? '', 0, 250));
        $lines = [
            $type === 'listed' ? '🚨 RBL Checker: IP listado' : '✅ RBL Checker: evento resolvido',
            '', 'Alvo: '.$safe($event->target?->name),
            'Valor monitorado: '.$safe($event->target?->value),
            ($type === 'listed' ? 'IP listado: ' : 'IP afetado: ').$safe($event->last_checked_value),
        ];
        if (config('rbl.include_group') && $event->target?->group) {
            $lines[] = 'Grupo: '.$safe($event->target->group->name);
        }
        $lines[] = 'Lista: '.$safe($event->list?->name);
        if ($type === 'listed' && config('rbl.include_response_codes')) {
            $lines[] = 'Resposta: '.$safe($event->last_response);
        }
        $lines[] = 'Primeira detecção: '.$event->first_seen_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');
        if ($type === 'resolved') {
            $lines[] = 'Resolvido em: '.$event->resolved_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');
            $lines[] = 'Duração aproximada: '.$event->durationMinutes().' min';
        } else {
            $lines[] = 'Ação sugerida: verificar origem do tráfego antes de solicitar delist.';
        }

        return implode("\n", $lines);
    }
}
