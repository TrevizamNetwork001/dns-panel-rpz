<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmpresaController extends Controller
{
    public function index(): View
    {
        $empresasAtivas = Empresa::where('status', 'active')->count();
        $empresas = Empresa::withCount('servidores')->orderBy('nome')->paginate(20);

        return view('empresas.index', compact('empresas', 'empresasAtivas'));
    }

    public function create(): View
    {
        $empresa = new Empresa();

        return view('empresas.form', compact('empresa'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $empresa = Empresa::create($data);

        AuditLog::record('empresa.created', "Empresa \"{$empresa->nome}\" criada", $empresa->id, 'empresa', $empresa->id);

        return redirect()->route('empresas.index')->with('status', 'Empresa criada com sucesso.');
    }

    public function show(Empresa $empresa): View
    {
        $user = Auth::user();

        if ($user->isCliente() && $user->empresa_id !== $empresa->id) {
            abort(403);
        }

        $empresa->load(['servidores.allowedIps', 'servidores.listas', 'listas', 'licencas', 'users']);

        return view('empresas.show', compact('empresa'));
    }

    public function edit(Empresa $empresa): View
    {
        return view('empresas.form', compact('empresa'));
    }

    public function update(Request $request, Empresa $empresa): RedirectResponse
    {
        $oldSlug = $empresa->rpz_slug;
        $data = $this->validated($request);

        $empresa->update($data);

        AuditLog::record('empresa.updated', "Empresa \"{$empresa->nome}\" atualizada", $empresa->id, 'empresa', $empresa->id);
        if ($oldSlug !== $empresa->rpz_slug) {
            AuditLog::record('rpz.endpoint.slug_changed', 'Slug do endpoint RPZ empresarial alterado', $empresa->id, 'empresa', $empresa->id);
        }

        return redirect()->route('empresas.index')->with('status', 'Empresa atualizada com sucesso.');
    }

    public function testRpzAcl(Request $request, Empresa $empresa): RedirectResponse
    {
        $data = $request->validate(['ip' => ['required', 'ip']]);
        $allowed = $empresa->servidores()
            ->where('status', 'active')
            ->whereHas('allowedIps', fn ($query) => $query->where('status', 'active'))
            ->with(['allowedIps' => fn ($query) => $query->where('status', 'active')])
            ->get()
            ->flatMap->allowedIps
            ->contains(fn ($rule): bool => \App\Models\Servidor::ipMatchesCidr($data['ip'], $rule->ip_cidr));

        return back()->with('acl_test', ['ip' => $data['ip'], 'allowed' => $allowed]);
    }

    public function destroy(Empresa $empresa): RedirectResponse
    {
        AuditLog::record('empresa.destroyed', "Empresa \"{$empresa->nome}\" removida", null, 'empresa', $empresa->id);

        $empresa->delete();

        return redirect()->route('empresas.index')->with('status', 'Empresa removida.');
    }

    private function validated(Request $request): array
    {
        $empresa = $request->route('empresa');
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'rpz_slug' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                Rule::unique('empresas', 'rpz_slug')->ignore($empresa?->id),
            ],
            'documento' => ['nullable', 'string', 'max:32'],
            'email_contato' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
