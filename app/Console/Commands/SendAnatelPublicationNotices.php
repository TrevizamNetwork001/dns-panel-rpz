<?php

namespace App\Console\Commands;

use App\Models\AnatelImport;
use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\RpzZone;
use App\Models\Setting;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendAnatelPublicationNotices extends Command implements Isolatable
{
    protected $signature = 'anatel:avisar-publicacao';

    protected $description = 'Envia e-mail após a aprovação e avisa no Telegram após a publicação do feed';

    public function handle(TelegramNotifier $telegram): int
    {
        if (! config('anatel.publication_notices_enabled') || ! config('rpz.static_artifacts_enabled') || ! config('rpz.endpoints_enabled')) {
            return self::SUCCESS;
        }

        AnatelImport::query()->with('lista')->where('from_mailbox', true)->where('status', 'completed')
            ->where(fn ($query) => $query->whereNull('telegram_notified_at')->orWhere(
                fn ($email) => $email->whereNull('email_notified_at')->whereNull('email_skipped_at'),
            ))
            ->chunkById(50, function ($imports) use ($telegram): void {
                foreach ($imports as $import) {
                    $this->notify($import, $telegram);
                }
            });

        return self::SUCCESS;
    }

    private function notify(AnatelImport $import, TelegramNotifier $telegram): void
    {
        $company = $import->reporting_empresa_id ? Empresa::find($import->reporting_empresa_id) : null;
        $contacts = $company?->emailsParaNotificacoes() ?? [];
        if (! $import->email_notified_at && ! $import->email_skipped_at && $contacts === []) {
            $import->forceFill(['email_skipped_at' => now()])->save();
        }
        if ($import->lista->status !== 'active' || ! config('rpz.publication_enabled')) {
            return;
        }
        $companies = $import->lista->servidores()->where('servidores.status', 'active')
            ->whereHas('empresa', fn ($query) => $query->where('status', 'active'))
            ->distinct()->pluck('servidores.empresa_id');

        if (! $import->email_notified_at && ! $import->email_skipped_at
            && $companies->contains($company->id) && $company->possuiLicencaAtiva()) {
            $this->sendEmail($import, $company, $contacts);
        }

        $keys = $companies->map(fn ($id) => 'company:'.$id);

        if (! $import->telegram_notified_at && $keys->isNotEmpty()
            && $keys->every(fn (string $key): bool => $this->hasPublishedFeed($key))
            && $telegram->notifyAnatelPublished($import->id, $import->new_count, $import->reactivated_count)) {
            $import->forceFill(['telegram_notified_at' => now()])->save();
        }
    }

    private function hasPublishedFeed(string $key): bool
    {
        $zone = RpzZone::with('currentVersion')->where('zone_key', $key)->first();
        $version = $zone?->currentVersion;

        return $zone !== null && ! $zone->needsRebuild()
            && $version?->status === 'published' && $version->rpz_zone_id === $zone->id
            && $version->file_path && Storage::disk('local')->exists($version->file_path);
    }

    private function sendEmail(AnatelImport $import, Empresa $company, array $contacts): void
    {
        $host = config('anatel.smtp_host');
        $account = Setting::get('anatel_imap_email');
        $password = Setting::getEncrypted('anatel_imap_password');
        if (! $host || ! $account || $password === null) {
            return;
        }

        $body = "Olá, {$company->nome},\n\nObrigado por nos encaminhar a notificação da Anatel. Concluímos a análise do material e a lista foi aprovada.\n\n"
            ."A publicação no feed RPZ da sua empresa está prevista para as próximas horas. Assim que a atualização estiver disponível, o seu servidor DNS poderá carregá-la na próxima sincronização.\n\n"
            ."Para acompanhar a solicitação, use a referência nº {$import->id}.\n\n"
            ."Atenciosamente,\nEquipe Trevizam Networks";
        try {
            $mailer = Mail::build([
                'transport' => 'smtp',
                'scheme' => 'smtps',
                'host' => $host,
                'port' => config('anatel.smtp_port'),
                'username' => $account,
                'password' => $password,
                'timeout' => 10,
            ]);
            $sent = $mailer->raw($body, function ($message) use ($account, $contacts, $import): void {
                $message->from($account, 'Trevizam DNS')->to($contacts[0])
                    ->subject("Lista Anatel aprovada para publicação no RPZ · Importação nº {$import->id}");
                if (count($contacts) > 1) {
                    $message->bcc(array_slice($contacts, 1));
                }
            });
            if ($sent !== null) {
                $import->forceFill(['email_notified_at' => now()])->save();
            }
        } catch (\Throwable) {
            AuditLog::record('anatel.imap.confirmation_failed', 'Falha no envio da confirmação por e-mail', $company->id, 'anatel_import', $import->id);
        }
    }
}
