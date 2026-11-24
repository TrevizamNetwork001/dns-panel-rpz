<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class AnatelImapAttachments
{
    /** @return list<UploadedFile> */
    public function latest(string $directory): array
    {
        $names = $this->receive($directory);
        if ($names === []) {
            throw ValidationException::withMessages(['imap' => 'O último e-mail na caixa de entrada não contém PDF ou planilha XLSX.']);
        }

        return $this->files($directory, $names);
    }

    public function scan(string $directory, ?array $cursor): array
    {
        return $this->receive($directory, ['cursor' => $cursor]);
    }

    /** @return list<UploadedFile> */
    public function files(string $directory, array $names): array
    {
        return array_map(
            fn (string $name, int $index) => new UploadedFile($directory.'/'.$index, $name, test: true),
            $names,
            array_keys($names),
        );
    }

    private function receive(string $directory, array $options = []): array
    {
        $settings = [
            'host' => Setting::get('anatel_imap_host'),
            'port' => (int) Setting::get('anatel_imap_port'),
            'email' => Setting::get('anatel_imap_email'),
            'password' => Setting::getEncrypted('anatel_imap_password'),
            'max_bytes' => (int) config('anatel.max_pdf_kb') * 1024,
            'max_files' => (int) config('anatel.max_files'),
        ];
        if (! $settings['host'] || ! $settings['port'] || ! $settings['email'] || $settings['password'] === null) {
            throw ValidationException::withMessages(['imap' => 'Salve as credenciais IMAP em Configurações → Integrações.']);
        }

        $process = new Process(
            [config('anatel.python_bin'), base_path('scripts/anatel_imap_attachments.py'), $directory],
            input: json_encode($settings + $options, JSON_THROW_ON_ERROR),
            timeout: $options === [] ? 20 : 100,
        );
        try {
            $success = $process->run() === 0;
        } catch (ProcessTimedOutException) {
            $success = false;
        }
        $names = $success ? json_decode($process->getOutput(), true) : null;
        if (! is_array($names)) {
            throw ValidationException::withMessages(['imap' => 'Falha ao consultar a caixa IMAP. Confira as credenciais e os limites dos anexos.']);
        }
        return $names;
    }
}
