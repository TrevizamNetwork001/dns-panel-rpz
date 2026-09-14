<?php

namespace App\Services;

use RuntimeException;

class AnatelPdfExtractor
{
    /**
     * Detecta o tipo real do arquivo pela assinatura binária (nunca pela extensão
     * informada pelo cliente). Retorna a extensão de armazenamento ou null se não
     * reconhecer nenhum formato suportado.
     */
    public function detectExtension(string $realPath): ?string
    {
        $head = @file_get_contents($realPath, false, null, 0, 8);
        if ($head === false) {
            return null;
        }
        if (str_starts_with($head, '%PDF-')) {
            return 'pdf';
        }
        // .xlsx é um arquivo ZIP (assinatura "PK"); confirma que é mesmo uma
        // planilha do Office e não outro formato baseado em ZIP (ex: .docx).
        if (str_starts_with($head, "PK\x03\x04") || str_starts_with($head, "PK\x05\x06")) {
            $zip = new \ZipArchive;
            if ($zip->open($realPath) === true) {
                $isXlsx = $zip->locateName('xl/workbook.xml') !== false;
                $zip->close();

                return $isXlsx ? 'xlsx' : null;
            }
        }

        return null;
    }

    public function extract(array $absolutePaths): array
    {
        $python = (string) config('anatel.python_bin');
        $script = base_path('scripts/anatel_pdf_extract.py');
        if (! is_file($python) || ! is_executable($python)) {
            throw new RuntimeException('Extrator ANATEL não está instalado.');
        }
        foreach ($absolutePaths as $path) {
            if (! is_string($path) || ! is_file($path) || is_link($path) || ! str_starts_with(realpath($path) ?: '', storage_path('app/private/anatel/'))) {
                throw new RuntimeException('Caminho de PDF inválido.');
            }
        }
        $command = array_merge([$python, $script], array_values($absolutePaths));
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (! is_resource($process)) {
            throw new RuntimeException('Não foi possível iniciar o extrator.');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $started = microtime(true);
        $timeout = max(10, (int) config('anatel.extract_timeout'));
        do {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if ((microtime(true) - $started) > $timeout) {
                proc_terminate($process, 9);
                foreach ([1, 2] as $i) {
                    fclose($pipes[$i]);
                } proc_close($process);
                throw new RuntimeException('Tempo limite excedido na extração.');
            }
            if ($status['running']) {
                usleep(20000);
            }
        } while ($status['running']);
        $stdout .= stream_get_contents($pipes[1]);
        foreach ([1, 2] as $i) {
            fclose($pipes[$i]);
        } $exit = proc_close($process);
        $data = json_decode($stdout, true);
        if (($exit !== 0 && $exit !== -1) || ! is_array($data) || ($data['status'] ?? null) !== 'ok' || ! is_array($data['domains'] ?? null)) {
            throw new RuntimeException('Falha segura no extrator de PDF.');
        }

        return $data;
    }
}
