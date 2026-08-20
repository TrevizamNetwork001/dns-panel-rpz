<?php

namespace App\Http\Controllers;

use App\Models\Dominio;
use App\Models\Lista;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DominioController extends Controller
{
    private const DOMAIN_REGEX = '/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.[a-z0-9-]{1,63})*\.[a-z]{2,63}$/i';

    public function index(Lista $lista): View
    {
        $dominios = $lista->dominios()->orderBy('dominio')->paginate(50);

        return view('dominios.index', compact('lista', 'dominios'));
    }

    public function store(Request $request, Lista $lista): RedirectResponse
    {
        $data = $request->validate([
            'dominio' => ['required', 'string', 'max:255'],
        ]);

        $normalized = $this->normalize($data['dominio']);

        if ($normalized === null) {
            return back()->withErrors(['dominio' => 'Domínio inválido.'])->withInput();
        }

        $lista->dominios()->firstOrCreate(['dominio' => $normalized], ['ativo' => true]);

        return redirect()->route('listas.dominios.index', $lista)->with('status', 'Domínio adicionado.');
    }

    public function bulkStore(Request $request, Lista $lista): RedirectResponse
    {
        $data = $request->validate([
            'dominios' => ['required', 'string'],
        ]);

        $lines = preg_split('/[\s,;]+/', $data['dominios'], -1, PREG_SPLIT_NO_EMPTY);

        $added = 0;
        $duplicated = 0;
        $invalid = 0;

        $existing = $lista->dominios()->pluck('dominio')->flip();

        foreach (array_unique($lines) as $line) {
            $normalized = $this->normalize($line);

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
        $dominio->update(['ativo' => ! $dominio->ativo]);

        return redirect()->route('listas.dominios.index', $dominio->lista_id)->with('status', 'Status do domínio atualizado.');
    }

    public function destroy(Dominio $dominio): RedirectResponse
    {
        $listaId = $dominio->lista_id;
        $dominio->delete();

        return redirect()->route('listas.dominios.index', $listaId)->with('status', 'Domínio removido.');
    }

    private function normalize(string $value): ?string
    {
        $value = strtolower(trim($value));
        $value = rtrim($value, '.');

        if ($value === '' || ! preg_match(self::DOMAIN_REGEX, $value)) {
            return null;
        }

        return $value;
    }
}
