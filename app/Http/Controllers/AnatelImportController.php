<?php

namespace App\Http\Controllers;

use App\Models\AnatelImport;
use App\Models\Lista;
use App\Services\AnatelPdfExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnatelImportController extends Controller
{
    public function store(Request $request, Lista $lista, AnatelPdfExtractor $extractor): RedirectResponse
    {
        abort_unless($lista->isAnatel(), 404);
        // Keep old upload URLs compatible with the same queue/review workflow.
        // The bound list is authoritative, never a caller-supplied lista_id.
        $request->merge(['lista_id' => $lista->id]);

        return app(AnatelDashboardController::class)->store($request, $extractor);
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
