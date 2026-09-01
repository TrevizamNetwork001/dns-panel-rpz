<?php

namespace App\Http\Controllers;

use App\Models\AnatelExclusion;
use App\Models\AuditLog;
use App\Models\Lista;
use App\Services\AnatelExclusionMatcher;
use App\Services\DomainNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnatelController extends Controller
{
    public function history(Lista $lista): View
    {
        $this->ensure($lista);
        $imports = $lista->anatelImports()->with('user')->latest()->paginate(30);

        return view('anatel.history', compact('lista', 'imports'));
    }

    public function exclusions(Lista $lista): View
    {
        $this->ensure($lista);
        $exclusions = $lista->anatelExclusions()->latest()->paginate(50);

        return view('anatel.exclusions', compact('lista', 'exclusions'));
    }

    public function storeExclusion(Request $request, Lista $lista, DomainNormalizer $normalizer, AnatelExclusionMatcher $matcher): RedirectResponse
    {
        $this->ensure($lista);
        $data = $request->validate(['type' => ['required', 'in:exact,regex'], 'value' => ['required', 'string', 'max:1000'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $value = trim($data['value']);
        if ($data['type'] === 'exact') {
            $value = $normalizer->normalize($value, false);
            if ($value === null) {
                throw ValidationException::withMessages(['value' => 'Domínio exato inválido.']);
            }
        } elseif (! $matcher->isValidRegex($value)) {
            throw ValidationException::withMessages(['value' => 'Regex inválida.']);
        }
        $exclusion = AnatelExclusion::firstOrCreate(['lista_id' => $lista->id, 'type' => $data['type'], 'value_hash' => hash('sha256', $value)], ['user_id' => $request->user()->id, 'value' => $value, 'active' => true, 'notes' => $data['notes'] ?? null]);
        if (! $exclusion->wasRecentlyCreated) {
            $exclusion->update(['active' => true, 'notes' => $data['notes'] ?? $exclusion->notes]);
        } $changed = $this->applyExclusion($lista, $exclusion, $matcher);
        AuditLog::record('anatel.exclusion.created', "Exclusão ANATEL criada ({$data['type']}), {$changed} domínio(s) desativado(s)", $lista->empresa_id, 'anatel_exclusion', $exclusion->id);

        return back()->with('status', "Exclusão salva; {$changed} domínio(s) desbloqueado(s).");
    }

    public function toggleExclusion(Lista $lista, AnatelExclusion $exclusion, AnatelExclusionMatcher $matcher): RedirectResponse
    {
        $this->ensureOwned($lista, $exclusion);
        $exclusion->update(['active' => ! $exclusion->active]);
        $changed = $exclusion->active ? $this->applyExclusion($lista, $exclusion, $matcher) : $this->reactivateEligible($lista, $matcher);
        AuditLog::record($exclusion->active ? 'anatel.exclusion.updated' : 'anatel.exclusion.disabled', "Exclusão ANATEL alterada; {$changed} domínio(s) afetado(s)", $lista->empresa_id, 'anatel_exclusion', $exclusion->id);

        return back()->with('status', 'Exclusão atualizada.');
    }

    public function destroyExclusion(Lista $lista, AnatelExclusion $exclusion, AnatelExclusionMatcher $matcher): RedirectResponse
    {
        $this->ensureOwned($lista, $exclusion);
        $id = $exclusion->id;
        $exclusion->delete();
        $changed = $this->reactivateEligible($lista, $matcher);
        AuditLog::record('anatel.exclusion.deleted', "Exclusão ANATEL removida; {$changed} domínio(s) reativado(s)", $lista->empresa_id, 'anatel_exclusion', $id);

        return back()->with('status', 'Exclusão removida.');
    }

    public function importLegacy(Request $request, Lista $lista, DomainNormalizer $normalizer, AnatelExclusionMatcher $matcher): RedirectResponse
    {
        $this->ensure($lista);
        $request->validate(['exclude_list' => ['required', 'file', 'max:1024']]);
        $lines = file($request->file('exclude_list')->getRealPath(), FILE_IGNORE_NEW_LINES) ?: [];
        $exact = 0;
        $regex = 0;
        $invalid = 0;
        $duplicates = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }$domain = $normalizer->normalize($line, false);
            $type = $domain === $line ? 'exact' : 'regex';
            $value = $type === 'exact' ? $domain : $line;
            if ($type === 'regex' && ! $matcher->isValidRegex($value)) {
                $invalid++;

                continue;
            }$item = AnatelExclusion::firstOrCreate(['lista_id' => $lista->id, 'type' => $type, 'value_hash' => hash('sha256', $value)], ['user_id' => $request->user()->id, 'value' => $value, 'active' => true]);
            if (! $item->wasRecentlyCreated) {
                $duplicates++;

                continue;
            }$type === 'exact' ? $exact++ : $regex++;
        }
        $changed = 0;
        foreach ($lista->anatelExclusions()->where('active', true)->get() as $item) {
            $changed += $this->applyExclusion($lista, $item, $matcher);
        }AuditLog::record('anatel.legacy_exclude_imported', "exclude_list importada: {$exact} exatos, {$regex} regex, {$duplicates} duplicados, {$invalid} inválidos", $lista->empresa_id, 'lista', $lista->id);

        return back()->with('status', "Importação concluída: {$exact} exatos, {$regex} regex, {$duplicates} duplicados, {$invalid} inválidos; {$changed} desbloqueados.");
    }

    private function applyExclusion(Lista $lista, AnatelExclusion $exclusion, AnatelExclusionMatcher $matcher): int
    {
        $count = 0;
        DB::table('dominios')->where('lista_id', $lista->id)->where('ativo', true)->orderBy('id')->chunkById(500, function ($rows) use ($exclusion, $matcher, &$count) {
            $ids = [];
            foreach ($rows as $row) {
                if ($matcher->matches($row->dominio, [$exclusion])) {
                    $ids[] = $row->id;
                }
            }if ($ids) {
                $count += count($ids);
                DB::table('dominios')->whereIn('id', $ids)->update(['ativo' => false, 'inactive_reason' => 'anatel_exclusion', 'updated_at' => now()]);
            }
        });

        return $count;
    }

    private function reactivateEligible(Lista $lista, AnatelExclusionMatcher $matcher): int
    {
        $active = $lista->anatelExclusions()->where('active', true)->get();
        $count = 0;
        DB::table('dominios')->where('lista_id', $lista->id)->where('inactive_reason', 'anatel_exclusion')->orderBy('id')->chunkById(500, function ($rows) use ($active, $matcher, &$count) {
            $ids = [];
            foreach ($rows as $row) {
                if (! $matcher->matches($row->dominio, $active)) {
                    $ids[] = $row->id;
                }
            }if ($ids) {
                $count += count($ids);
                DB::table('dominios')->whereIn('id', $ids)->update(['ativo' => true, 'inactive_reason' => null, 'updated_at' => now()]);
            }
        });

        return $count;
    }

    private function ensure(Lista $lista): void
    {
        abort_unless($lista->isAnatel(),404);
    }

    private function ensureOwned(Lista $lista,AnatelExclusion $exclusion): void
    {
        $this->ensure($lista);
        abort_unless($exclusion->lista_id === $lista->id,404);
    }
}
