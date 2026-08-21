<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use App\Services\ExternalListaSyncer;
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

        AuditLog::record('lista.created', "Lista \"{$lista->nome}\" criada", $lista->empresa_id, 'lista', $lista->id);

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

        AuditLog::record('lista.updated', "Lista \"{$lista->nome}\" atualizada", $lista->empresa_id, 'lista', $lista->id);

        return redirect()->route('listas.show', $lista)->with('status', 'Lista atualizada com sucesso.');
    }

    public function destroy(Lista $lista): RedirectResponse
    {
        if ($lista->isExterna()) {
            return back()->withErrors(['lista' => 'Listas de fonte externa não podem ser removidas por aqui. Desative a sincronização em vez disso.']);
        }

        AuditLog::record('lista.destroyed', "Lista \"{$lista->nome}\" removida", $lista->empresa_id, 'lista', $lista->id);

        $lista->delete();

        return redirect()->route('listas.index')->with('status', 'Lista removida.');
    }

    public function toggleSync(Lista $lista): RedirectResponse
    {
        if (! $lista->isExterna()) {
            abort(404);
        }

        $lista->update(['sync_ativo' => ! $lista->sync_ativo]);

        AuditLog::record(
            $lista->sync_ativo ? 'lista.externa.sync_habilitado' : 'lista.externa.sync_pausado',
            "Sincronização automática da lista \"{$lista->nome}\" " . ($lista->sync_ativo ? 'reativada' : 'pausada'),
            null,
            'lista',
            $lista->id
        );

        return back()->with('status', $lista->sync_ativo
            ? 'Sincronização automática reativada.'
            : 'Sincronização automática pausada — a lista fica como está até você reativar.');
    }

    public function syncNow(Lista $lista, ExternalListaSyncer $syncer): RedirectResponse
    {
        if (! $lista->isExterna()) {
            abort(404);
        }

        $resultado = $syncer->syncAndLog($lista);

        return match ($resultado['status']) {
            'ok' => back()->with('status', "Sincronizado agora: {$resultado['total']} ativos, {$resultado['adicionados']} novos, {$resultado['removidos']} desativados."),
            'pausada' => back()->withErrors(['lista' => 'Sincronização está pausada — reative antes de sincronizar.']),
            'erro' => back()->withErrors(['lista' => 'Falha ao sincronizar: ' . ($resultado['motivo'] ?? 'erro desconhecido')]),
            default => back(),
        };
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
            'origem' => ['nullable', 'in:manual,externa'],
            'fonte_url' => ['nullable', 'url', 'max:500', 'required_if:origem,externa'],
            'fonte_formato' => ['nullable', 'in:hostfile,plain,unbound_local_zone'],
        ]);

        $data['empresa_id'] = $data['empresa_id'] ?? null;
        $data['origem'] = $data['origem'] ?? 'manual';

        if ($data['origem'] === 'externa') {
            $data['fonte_formato'] = $data['fonte_formato'] ?? 'hostfile';
            $data['fonte_externa'] = $data['fonte_externa'] ?? 'custom';
            $data['sync_ativo'] = true;
        } else {
            $data['fonte_url'] = null;
            $data['fonte_externa'] = null;
        }

        return $data;
    }
}
