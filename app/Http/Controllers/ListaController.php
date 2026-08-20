<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ListaController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $query = Lista::with('empresa')->orderByDesc('id');

        if ($user->isCliente()) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('empresa_id')->orWhere('empresa_id', $user->empresa_id);
            });
        }

        $listas = $query->paginate(20);

        return view('listas.index', compact('listas'));
    }

    public function create(): View
    {
        $lista = new Lista();
        $empresas = Empresa::orderBy('nome')->get();

        return view('listas.form', compact('lista', 'empresas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $servidorIds = $request->input('servidor_ids', []);

        $lista = Lista::create($data);
        $lista->servidores()->sync($servidorIds);

        return redirect()->route('listas.show', $lista)->with('status', 'Lista criada com sucesso.');
    }

    public function show(Lista $lista): View
    {
        $this->authorizeAccess($lista);

        $lista->load(['empresa', 'servidores.empresa', 'dominios']);

        return view('listas.show', compact('lista'));
    }

    public function edit(Lista $lista): View
    {
        $empresas = Empresa::orderBy('nome')->get();
        $lista->load('servidores');

        $servidoresDisponiveis = $lista->empresa_id
            ? Servidor::where('empresa_id', $lista->empresa_id)->orderBy('nome')->get()
            : Servidor::with('empresa')->orderBy('nome')->get();

        return view('listas.form', compact('lista', 'empresas', 'servidoresDisponiveis'));
    }

    public function update(Request $request, Lista $lista): RedirectResponse
    {
        $data = $this->validated($request);
        $servidorIds = $request->input('servidor_ids', []);

        $lista->update($data);
        $lista->servidores()->sync($servidorIds);

        return redirect()->route('listas.show', $lista)->with('status', 'Lista atualizada com sucesso.');
    }

    public function destroy(Lista $lista): RedirectResponse
    {
        $lista->delete();

        return redirect()->route('listas.index')->with('status', 'Lista removida.');
    }

    private function authorizeAccess(Lista $lista): void
    {
        $user = Auth::user();

        if ($user->isCliente() && $lista->empresa_id !== null && $lista->empresa_id !== $user->empresa_id) {
            abort(403);
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $data['empresa_id'] = $data['empresa_id'] ?: null;

        return $data;
    }
}
