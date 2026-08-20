<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Licenca;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LicencaController extends Controller
{
    public function index(): View
    {
        $licencas = Licenca::with('empresa')->orderByDesc('id')->paginate(20);

        return view('licencas.index', compact('licencas'));
    }

    public function create(): View
    {
        $licenca = new Licenca();
        $empresas = Empresa::orderBy('nome')->get();

        return view('licencas.form', compact('licenca', 'empresas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $licenca = Licenca::create($data);

        AuditLog::record('licenca.created', "Licença criada para empresa #{$licenca->empresa_id} (max {$licenca->max_servidores} servidores)", $licenca->empresa_id, 'licenca', $licenca->id);

        return redirect()->route('licencas.index')->with('status', 'Licença criada com sucesso.');
    }

    public function show(Licenca $licenca): View
    {
        $licenca->load('empresa');

        return view('licencas.show', compact('licenca'));
    }

    public function edit(Licenca $licenca): View
    {
        $empresas = Empresa::orderBy('nome')->get();

        return view('licencas.form', compact('licenca', 'empresas'));
    }

    public function update(Request $request, Licenca $licenca): RedirectResponse
    {
        $data = $this->validated($request);

        $licenca->update($data);

        AuditLog::record('licenca.updated', "Licença #{$licenca->id} atualizada (max {$licenca->max_servidores} servidores, status {$licenca->status})", $licenca->empresa_id, 'licenca', $licenca->id);

        return redirect()->route('licencas.index')->with('status', 'Licença atualizada com sucesso.');
    }

    public function destroy(Licenca $licenca): RedirectResponse
    {
        AuditLog::record('licenca.destroyed', "Licença #{$licenca->id} removida", $licenca->empresa_id, 'licenca', $licenca->id);

        $licenca->delete();

        return redirect()->route('licencas.index')->with('status', 'Licença removida.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_servidores' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,inactive,expired'],
        ]);
    }
}
