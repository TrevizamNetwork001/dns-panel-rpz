<?php

namespace App\Http\Controllers;

use App\Models\Lista;
use App\Services\DomainNormalizer;
use App\Services\RpzZoneBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RpzPreviewController extends Controller
{
    public function show(Request $request, Lista $lista, RpzZoneBuilder $builder, DomainNormalizer $normalizer): View
    {
        $generatedAt = now();
        $serial = (string) $generatedAt->timestamp;
        $stats = $builder->stats($lista);
        $preview = $builder->preview($lista, 500, $serial, $stats['entries']);
        $search = $this->search($request, $lista, $builder, $normalizer);

        return view('listas.rpz-preview', compact('lista', 'stats', 'preview', 'search', 'generatedAt'));
    }

    public function download(Lista $lista, RpzZoneBuilder $builder): StreamedResponse
    {
        $filename = preg_replace('/[^a-z0-9-]+/', '-', strtolower($lista->nome)) ?: 'lista';
        $filename = trim($filename, '-').'-rpz-preview-'.now()->format('Ymd').'.zone';
        $serial = (string) now()->timestamp;

        return response()->streamDownload(function () use ($builder, $lista, $serial): void {
            foreach ($builder->lines($builder->listQuery($lista), '.', $serial) as $line) {
                echo $line."\n";
            }
        }, $filename, ['Content-Type' => 'text/dns; charset=utf-8']);
    }

    private function search(Request $request, Lista $lista, RpzZoneBuilder $builder, DomainNormalizer $normalizer): ?array
    {
        if (! $request->filled('domain')) {
            return null;
        }

        $request->validate(['domain' => ['required', 'string', 'max:2048']]);
        $domain = $normalizer->normalize((string) $request->input('domain'));
        if ($domain === null) {
            return ['domain' => trim((string) $request->input('domain')), 'state' => 'invalid'];
        }

        $record = DB::table('dominios')->where('lista_id', $lista->id)->whereRaw('LOWER(dominio) = ?', [$domain])->first();
        if (! $record) {
            return ['domain' => $domain, 'state' => 'missing'];
        }

        if (! $record->ativo) {
            return [
                'domain' => $domain,
                'state' => $record->inactive_reason === 'anatel_exclusion' ? 'excluded' : 'inactive',
                'reason' => $record->inactive_reason,
            ];
        }

        return ['domain' => $domain, 'state' => 'included', 'rule' => $domain.' CNAME '.$builder->target()];
    }
}
