<?php

namespace App\Http\Controllers;

use App\Models\AnatelImport;
use App\Models\AuditLog;
use App\Models\Lista;
use App\Services\AnatelImporter;
use App\Services\AnatelPdfExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnatelImportController extends Controller
{
    public function store(Request $request, Lista $lista, AnatelPdfExtractor $extractor, AnatelImporter $importer): RedirectResponse
    {
        abort_unless($lista->isAnatel(), 404);
        $request->validate(['pdfs' => ['required', 'array', 'min:1', 'max:'.config('anatel.max_files')], 'pdfs.*' => ['required', 'file', 'mimetypes:application/pdf,application/x-pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip', 'max:'.config('anatel.max_pdf_kb')]]);
        $completed = [];
        foreach ($request->file('pdfs') as $upload) {
            $real = $upload->getRealPath();
            $extension = $real ? $extractor->detectExtension($real) : null;
            if ($extension === null) {
                throw ValidationException::withMessages(['pdfs' => 'O conteúdo enviado não é um PDF nem uma planilha Excel (.xlsx) válida.']);
            }
            $sha = hash_file('sha256', $real);
            if (AnatelImport::where('sha256', $sha)->whereIn('status', ['pending', 'processing', 'awaiting_approval', 'completed'])->exists()) {
                throw ValidationException::withMessages(['pdfs' => 'Este arquivo já foi processado.']);
            }
            $path = $upload->storeAs(date('Y/m'), $sha.'.'.$extension, 'anatel');
            try {
                $import = AnatelImport::create(['lista_id' => $lista->id, 'user_id' => $request->user()->id, 'original_filename' => basename($upload->getClientOriginalName()), 'storage_path' => $path, 'sha256' => $sha, 'size_bytes' => $upload->getSize(), 'status' => 'processing', 'started_at' => now()]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                Storage::disk('anatel')->delete($path);
                throw ValidationException::withMessages(['pdfs' => 'Este arquivo já foi processado.']);
            }
            AuditLog::record('anatel.import.started', 'Importação ANATEL iniciada: '.$import->original_filename, $lista->empresa_id, 'anatel_import', $import->id);
            try {
                $payload = $extractor->extract([Storage::disk('anatel')->path($path)]);
                $importer->apply($lista, $import, $payload);
                AuditLog::record('anatel.import.completed', "Importação ANATEL concluída: {$import->fresh()->new_count} novos", $lista->empresa_id, 'anatel_import', $import->id);
                $completed[] = $import->id;
            } catch (\Throwable $e) {
                if ($import->fresh()->status !== 'blocked') {
                    $import->update(['status' => 'failed', 'error' => 'Falha no processamento do PDF.', 'finished_at' => now()]);
                } $action = $import->fresh()->status === 'blocked' ? 'anatel.import.blocked' : 'anatel.import.failed';
                AuditLog::record($action, 'Importação ANATEL não concluída', $lista->empresa_id, 'anatel_import', $import->id);
            }
        }

        return redirect()->route('anatel.history', $lista)->with('status', count($completed).' PDF(s) importado(s).');
    }

    public function downloadNew(Lista $lista, AnatelImport $import): StreamedResponse
    {
        abort_unless($lista->isAnatel() && $import->lista_id === $lista->id && $import->status === 'completed', 404);

        return response()->streamDownload(function () use ($import) {
            $import->domains()->where('result', 'new')->orderBy('domain')->select('domain')->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    echo $row->domain."\n";
                }
            });
        }, 'anatel-novos-'.$import->id.'.txt', ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
