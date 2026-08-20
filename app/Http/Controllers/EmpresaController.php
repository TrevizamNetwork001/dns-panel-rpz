<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmpresaController extends Controller
{
    public function index(): View
    {
        $empresas = Empresa::orderBy('nome')->paginate(20);

        return view('empresas.index', compact('empresas'));
    }

    public function create(): View
    {
        $empresa = new Empresa();

        return view('empresas.form', compact('empresa'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Empresa::create($data);

        return redirect()->route('empresas.index')->with('status', 'Empresa criada com sucesso.');
    }

    public function show(Empresa $empresa): View
    {
        $user = Auth::user();

        if ($user->isCliente() && $user->empresa_id !== $empresa->id) {
            abort(403);
        }

        $empresa->load(['servidores', 'listas', 'licencas']);

        return view('empresas.show', compact('empresa'));
    }

    public function edit(Empresa $empresa): View
    {
        return view('empresas.form', compact('empresa'));
    }

    public function update(Request $request, Empresa $empresa): RedirectResponse
    {
        $data = $this->validated($request);

        $empresa->update($data);

        return redirect()->route('empresas.index')->with('status', 'Empresa atualizada com sucesso.');
    }

    public function destroy(Empresa $empresa): RedirectResponse
    {
        $empresa->delete();

        return redirect()->route('empresas.index')->with('status', 'Empresa removida.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:32'],
            'email_contato' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
