<?php

namespace App\Services;

use App\Models\AnatelImport;
use Illuminate\Validation\ValidationException;

class AnatelUploadValidator
{
    /** Validate the WHOLE selection before storing files or queueing any job. */
    public function prepare(array $uploads, AnatelPdfExtractor $extractor): array
    {
        $prepared = [];
        $seen = [];
        foreach ($uploads as $upload) {
            $real = $upload->getRealPath();
            $extension = $real ? $extractor->detectExtension($real) : null;
            if ($extension === null) {
                throw ValidationException::withMessages(['pdfs' => 'O conteúdo enviado não é um PDF nem uma planilha Excel (.xlsx) válida.']);
            }
            $sha = hash_file('sha256', $real);
            if (isset($seen[$sha]) || AnatelImport::where('sha256', $sha)->exists()) {
                throw ValidationException::withMessages(['pdfs' => 'Um dos arquivos já foi enviado. Para reenviar uma importação rejeitada ou com falha, exclua a prévia anterior primeiro.']);
            }
            $seen[$sha] = true;
            $prepared[] = [$upload, $extension, $sha];
        }

        return $prepared;
    }
}
