<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAnatelImport;
use App\Models\AnatelImport;
use App\Models\AuditLog;
use App\Models\Lista;
use App\Services\AnatelImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnatelDashboardController extends Controller
{
    public function index(): View
    {
        $listas = Lista::where('origem', 'anatel')->withCount(['dominios as dominios_ativos_count' => fn ($q) => $q->where('ativo', true), 'servidores', 'anatelImports as anatel_pending_count' => fn ($q) => $q->where('status', 'awaiting_approval')])->orderBy('nome')->get();
        $imports = AnatelImport::with(['lista' => fn ($q) => $q->withCount(['dominios as dominios_ativos_count' => fn ($d) => $d->where('ativo', true)])])->latest()->limit(20)->get();

        return view('anatel.index', compact('listas', 'imports'));
    }

    public function store(Request $request, \App\Services\AnatelPdfExtractor $extractor): RedirectResponse
    {
        $data = $request->validate(['lista_id' => ['required', 'exists:listas,id'], 'pdfs' => ['required', 'array', 'min:1', 'max:'.config('anatel.max_files')], 'pdfs.*' => ['required', 'file', 'mimetypes:application/pdf,application/x-pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip', 'max:'.config('anatel.max_pdf_kb')]]);
        $lista = Lista::findOrFail($data['lista_id']);
        abort_unless($lista->isAnatel(), 422);
        $ids = [];
        foreach ($request->file('pdfs') as $upload) {
            $real = $upload->getRealPath();
            $extension = $real ? $extractor->detectExtension($real) : null;
            if ($extension === null) {
                throw ValidationException::withMessages(['pdfs' => 'O conteúdo enviado não é um PDF nem uma planilha Excel (.xlsx) válida.']);
            }$sha = hash_file('sha256', $real);
            if (AnatelImport::where('sha256', $sha)->whereIn('status', ['pending', 'processing', 'completed'])->exists()) {
                throw ValidationException::withMessages(['pdfs' => 'Um dos arquivos já foi processado.']);
            }$path = $upload->storeAs(date('Y/m'), $sha.'.'.$extension, 'anatel');
            $import = AnatelImport::create(['lista_id' => $lista->id, 'user_id' => $request->user()->id, 'original_filename' => basename($upload->getClientOriginalName()), 'storage_path' => $path, 'sha256' => $sha, 'size_bytes' => $upload->getSize(), 'status' => 'pending', 'progress' => 0]);
            AuditLog::record('anatel.import.started', 'Importação ANATEL enfileirada: '.$import->original_filename, $lista->empresa_id, 'anatel_import', $import->id);
            ProcessAnatelImport::dispatch($import->id);
            $ids[] = $import->id;
        }

        return redirect()->route('anatel.dashboard', ['watch' => implode(',', $ids)])->with('status', 'PDF(s) recebido(s). Processamento iniciado.');
    }

    public function status(AnatelImport $import): JsonResponse
    {
        $import->load('lista');

        return response()->json(['id' => $import->id, 'status' => $import->status, 'status_label' => $import->status_label, 'progress' => $import->progress, 'filename' => $import->original_filename, 'finished_at' => optional($import->finished_at)->format('d/m/Y H:i:s'), 'new' => $import->new_count, 'existing' => $import->existing_count, 'reactivated' => $import->reactivated_count, 'excluded' => $import->excluded_count, 'invalid' => $import->invalid_count, 'total_active' => $import->lista->dominios()->where('ativo', true)->count(), 'endpoints' => $import->lista->servidores()->count(), 'preview_url' => $import->status === 'awaiting_approval' ? route('anatel.preview', $import) : null, 'new_url' => $import->status === 'completed' ? route('anatel.imports.new', [$import->lista, $import]) : null]);
    }

    public function preview(AnatelImport $import, Request $request): View
    {
        $filter = $request->input('result');
        $domains = $import->domains()->when(in_array($filter, ['new', 'existing', 'reactivated', 'excluded'], true), fn ($q) => $q->where('result', $filter))->orderBy('domain')->paginate(100)->withQueryString();
        $import->load('lista');

        return view('anatel.preview', compact('import', 'domains', 'filter'));
    }

    public function approve(AnatelImport $import, AnatelImporter $importer): RedirectResponse
    {
        $import->load('lista');
        $importer->approve($import);
        AuditLog::record('anatel.import.completed', "Importação ANATEL aprovada: {$import->fresh()->new_count} novos", $import->lista->empresa_id, 'anatel_import', $import->id);

        return redirect()->route('anatel.preview', $import)->with('status', 'Importação aprovada. A lista foi atualizada e estará nos endpoints vinculados na próxima sincronização.');
    }

    public function reject(AnatelImport $import): RedirectResponse
    {
        abort_unless($import->status === 'awaiting_approval', 422);
        $import->update(['status' => 'rejected']);
        AuditLog::record('anatel.import.rejected', 'Prévia ANATEL rejeitada; lista não alterada', $import->lista->empresa_id, 'anatel_import', $import->id);

        return redirect()->route('anatel.dashboard')->with('status', 'Importação rejeitada. Nenhum domínio foi alterado.');
    }

    public function destroyPreview(AnatelImport $import): RedirectResponse
    {
        abort_unless(in_array($import->status, ['awaiting_approval', 'rejected', 'failed', 'blocked'], true), 422);
        $filename = $import->original_filename;
        $lista = $import->lista;
        Storage::disk('anatel')->delete($import->storage_path);
        AuditLog::record('anatel.import.deleted', 'Prévia e PDF ANATEL removidos: '.$filename, $lista->empresa_id, 'lista', $lista->id);
        $import->delete();

        return redirect()->route('anatel.dashboard')->with('status', 'Prévia e PDF privado removidos. A lista não foi alterada.');
    }

    public function downloadPreview(AnatelImport $import): StreamedResponse
    {
        return response()->streamDownload(function () use ($import) {
            $import->domains()->orderBy('domain')->select(['domain', 'result'])->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    echo $row->domain."\t".$row->result."\n";
                }
            });
        }, 'anatel-previa-'.$import->id.'.txt', ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function batchPreview(Lista $lista): View
    {
        abort_unless($lista->isAnatel(), 404);
        $pending = $lista->anatelImports()->where('status', 'awaiting_approval')->orderBy('id')->get();
        $ids = $pending->pluck('id');
        $domains = DB::table('anatel_import_domains')->whereIn('anatel_import_id', $ids)->select('domain')->selectRaw("CASE WHEN SUM(CASE WHEN result = 'excluded' THEN 1 ELSE 0 END) > 0 THEN 'excluded' WHEN SUM(CASE WHEN result = 'new' THEN 1 ELSE 0 END) > 0 THEN 'new' WHEN SUM(CASE WHEN result = 'reactivated' THEN 1 ELSE 0 END) > 0 THEN 'reactivated' ELSE 'existing' END as result")->selectRaw('COUNT(*) as occurrences')->groupBy('domain')->orderBy('domain')->paginate(100);

        return view('anatel.batch', compact('lista', 'pending', 'domains'));
    }

    public function publishBatch(Lista $lista, AnatelImporter $importer): RedirectResponse
    {
        abort_unless($lista->isAnatel(), 404);
        $summary = DB::transaction(function () use ($lista, $importer) {
            $imports = $lista->anatelImports()->where('status', 'awaiting_approval')->lockForUpdate()->orderBy('id')->get();
            abort_if($imports->isEmpty(), 422, 'Nenhuma prévia pendente.');
            $ids = $imports->pluck('id')->all();
            foreach ($imports as $import) {
                $import->setRelation('lista', $lista);
                $importer->approve($import);
            }

return ['ids' => $ids, 'files' => count($ids), 'new' => $imports->sum('new_count'), 'valid' => $imports->sum('valid_count')];
        });
        AuditLog::record('anatel.batch.published', 'Lote ANATEL publicado: '.$summary['files'].' PDFs, IDs '.implode(',', $summary['ids']).', '.$summary['valid'].' válidos, '.$summary['new'].' novos', $lista->empresa_id, 'lista', $lista->id);

        return redirect()->route('anatel.dashboard')->with('status', 'Lote publicado com sucesso. Os endpoints vinculados receberão a atualização na próxima sincronização.');
    }

    public function rejectBatch(Lista $lista): RedirectResponse
    {
        abort_unless($lista->isAnatel(), 404);
        $imports = $lista->anatelImports()->where('status', 'awaiting_approval')->get();
        abort_if($imports->isEmpty(), 422, 'Nenhuma prévia pendente.');
        $ids = $imports->pluck('id')->all();
        $lista->anatelImports()->whereIn('id',$ids)->update(['status' => 'rejected']);
        AuditLog::record('anatel.batch.rejected','Lote ANATEL rejeitado: '.count($ids).' PDFs, IDs '.implode(',',$ids).'; lista não alterada',$lista->empresa_id,'lista',$lista->id);

        return redirect()->route('anatel.dashboard')->with('status','Lote rejeitado. Nenhum domínio foi enviado ao RPZ.');
    }
}
