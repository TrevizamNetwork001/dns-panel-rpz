<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAnatelImport;
use App\Models\AnatelImport;
use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Setting;
use App\Models\User;
use App\Services\AnatelImapAttachments;
use App\Services\AnatelPdfExtractor;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class ScanAnatelMailbox extends Command implements Isolatable
{
    protected $signature = 'anatel:consultar-caixa {--dry-run}';

    protected $description = 'Consulta a caixa IMAP e prepara prévias de notificações Anatel';

    public function handle(AnatelImapAttachments $imap, AnatelPdfExtractor $extractor): int
    {
        $lista = Lista::where('origem', 'anatel')->where('status', 'active')->find(config('anatel.imap_list_id'));
        if ($lista === null) {
            $this->error('Configure a fonte ANATEL de destino.');

            return self::FAILURE;
        }

        $directory = sys_get_temp_dir().'/anatel-inbox-'.bin2hex(random_bytes(16));
        File::makeDirectory($directory, 0700);
        try {
            $mailboxKey = hash('sha256', implode('|', [
                Setting::get('anatel_imap_host'),
                Setting::get('anatel_imap_port'),
                Setting::get('anatel_imap_email'),
            ]));
            $cursorKey = 'anatel_imap_cursor_'.$mailboxKey;
            $cursor = json_decode(Setting::get($cursorKey, 'null'), true);
            $scan = $imap->scan($directory, $cursor);
            $matched = 0;
            foreach ($scan['messages'] as $message) {
                $this->processMessage($imap, $extractor, $directory, $lista, $cursorKey, $scan['epoch'], $message);
                $matched += (int) ($message['state'] === 'matched');
            }
            $this->info(count($scan['messages'])." mensagem(ns) verificadas, {$matched} com anexos para análise.");

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error($exception->validator->errors()->first());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function processMessage(
        AnatelImapAttachments $imap,
        AnatelPdfExtractor $extractor,
        string $directory,
        Lista $lista,
        string $cursorKey,
        int $epoch,
        array $message,
    ): void {
        $uid = $message['uid'];
        $uploads = $imap->files($directory.'/'.$uid, $message['files']);
        if ($this->option('dry-run')) {
            return;
        }

        $companyId = $this->identifyCompany($message['sender'] ?? '');
        DB::transaction(function () use ($uploads, $extractor, $lista, $cursorKey, $epoch, $uid, $message, $companyId): void {
            foreach ($uploads as $upload) {
                $path = $upload->getPathname();
                $extension = $extractor->detectExtension($path);
                if ($extension === null) {
                    AuditLog::record('anatel.imap.review', "Mensagem IMAP {$uid} com anexo inválido", $companyId);

                    continue;
                }

                $sha = hash_file('sha256', $path);
                if (AnatelImport::where('sha256', $sha)->exists()) {
                    continue;
                }
                $stored = $upload->storeAs(now()->format('Y/m'), $sha.'.'.$extension, 'anatel');
                if ($stored === false) {
                    throw new \RuntimeException('Falha ao armazenar anexo ANATEL.');
                }
                $import = new AnatelImport([
                    'lista_id' => $lista->id,
                    'original_filename' => $upload->getClientOriginalName(),
                    'storage_path' => $stored,
                    'sha256' => $sha,
                    'size_bytes' => $upload->getSize(),
                    'status' => 'pending',
                    'progress' => 0,
                ]);
                $import->forceFill(['from_mailbox' => true, 'reporting_empresa_id' => $companyId])->save();
                ProcessAnatelImport::dispatch($import->id)->beforeCommit();
                AuditLog::record('anatel.imap.received', 'Anexo IMAP enfileirado para prévia', $companyId, 'anatel_import', $import->id);
            }
            if (in_array($message['state'], ['missing_attachment', 'oversized'], true)) {
                AuditLog::record('anatel.imap.review', "Mensagem IMAP {$uid} sem anexo utilizável", $companyId);
            }
            Setting::set($cursorKey, json_encode(['epoch' => $epoch, 'uid' => $uid], JSON_THROW_ON_ERROR));
        });
    }

    private function identifyCompany(string $sender): ?int
    {
        if (! filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $email = strtolower($sender);
        $ids = Empresa::whereRaw('LOWER(email_contato) = ?', [$email])->orWhereJsonContains('emails_adicionais', $email)->pluck('id')
            ->concat(User::where('role', 'cliente')->whereNotNull('empresa_id')->whereRaw('LOWER(email) = ?', [$email])->pluck('empresa_id'))
            ->unique();

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }
}
