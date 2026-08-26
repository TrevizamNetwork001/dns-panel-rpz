<?php

namespace App\Http\Controllers;

use App\Models\Dominio;
use App\Models\Lista;
use App\Services\DomainNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DominioController extends Controller
{
    public function __construct(private DomainNormalizer $normalizer) {}

    public function index(Lista $lista): View
    {
        $dominios = $lista->dominios()->orderBy('dominio')->paginate(50);

        return view('dominios.index', compact('lista', 'dominios'));
    }

    public function store(Request $request, Lista $lista): RedirectResponse
    {
        if ($lista->isManaged()) {
            return back()->withErrors(['dominio' => 'Esta lista é sincronizada automaticamente de uma fonte externa e não pode ser editada manualmente.']);
        }

        $data = $request->validate([
            'dominio' => ['required', 'string', 'max:255'],
        ]);

        $normalized = $this->normalizer->normalize($data['dominio']);

        if ($normalized === null) {
            return back()->withErrors(['dominio' => 'Domínio inválido.'])->withInput();
        }

        $dominio = $lista->dominios()->where('dominio', $normalized)->first();

        if ($dominio === null) {
            $lista->dominios()->create(['dominio' => $normalized, 'ativo' => true]);

            return redirect()->route('listas.dominios.index', $lista)->with('status', 'Domínio adicionado.');
        }

        if (! $dominio->ativo) {
            $dominio->update(['ativo' => true]);

            return redirect()->route('listas.dominios.index', $lista)->with('status', 'Domínio já existia nesta fonte e estava inativo — foi reativado.');
        }

        return redirect()->route('listas.dominios.index', $lista)->with('status', 'Domínio já cadastrado e ativo nesta fonte — nada foi alterado.');
    }

    public function bulkStore(Request $request, Lista $lista): RedirectResponse
    {
        if ($lista->isManaged()) {
            return back()->withErrors(['dominios' => 'Esta lista é sincronizada automaticamente de uma fonte externa e não pode ser editada manualmente.']);
        }

        $data = $request->validate([
            'dominios' => ['required', 'string'],
        ]);

        $lines = preg_split('/[\s,;]+/', $data['dominios'], -1, PREG_SPLIT_NO_EMPTY);

        $added = 0;
        $duplicated = 0;
        $invalid = 0;

        $existing = $lista->dominios()->pluck('dominio')->flip();

        foreach (array_unique($lines) as $line) {
            $normalized = $this->normalizer->normalize($line);

            if ($normalized === null) {
                $invalid++;
                continue;
            }

            if ($existing->has($normalized)) {
                $duplicated++;
                continue;
            }

            $lista->dominios()->create(['dominio' => $normalized, 'ativo' => true]);
            $existing->put($normalized, true);
            $added++;
        }

        return redirect()->route('listas.dominios.index', $lista)
            ->with('status', "{$added} domínio(s) adicionado(s), {$duplicated} já existia(m), {$invalid} inválido(s).");
    }

    public function toggle(Dominio $dominio): RedirectResponse
    {
        if ($dominio->lista->isManaged()) {
            return back()->withErrors(['dominio' => 'Esta lista é sincronizada automaticamente de uma fonte externa e não pode ser editada manualmente.']);
        }

        $dominio->update(['ativo' => ! $dominio->ativo]);

        return redirect()->route('listas.dominios.index', $dominio->lista_id)->with('status', 'Status do domínio atualizado.');
    }

    public function destroy(Dominio $dominio): RedirectResponse
    {
        if ($dominio->lista->isManaged()) {
            return back()->withErrors(['dominio' => 'Esta lista é sincronizada automaticamente de uma fonte externa e não pode ser editada manualmente.']);
        }

        $listaId = $dominio->lista_id;
        $dominio->delete();

        return redirect()->route('listas.dominios.index', $listaId)->with('status', 'Domínio removido.');
    }

}
