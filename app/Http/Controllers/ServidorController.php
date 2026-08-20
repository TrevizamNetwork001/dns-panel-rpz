<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ServidorController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $query = Servidor::with(['empresa', 'listas'])->orderByDesc('id');

        if ($user->isCliente()) {
            $query->where('empresa_id', $user->empresa_id);
        }

        $servidores = $query->paginate(20);

        return view('servidores.index', compact('servidores'));
    }

    public function create(): View
    {
        $user = Auth::user();
        $servidor = new Servidor();

        if ($user->isCliente()) {
            $empresas = collect([$user->empresa])->filter();
            $licencaBlocker = $this->licencaBlocker($user->empresa);
        } else {
            $empresas = Empresa::orderBy('nome')->get();
            $licencaBlocker = null;
        }

        return view('servidores.form', compact('servidor', 'empresas', 'licencaBlocker'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $this->validated($request, $user);

        if ($user->isCliente()) {
            $data['empresa_id'] = $user->empresa_id;

            if ($blocker = $this->licencaBlocker($user->empresa)) {
                return back()->withErrors(['empresa_id' => $blocker])->withInput();
            }
        }

        $servidor = Servidor::create($data);

        return redirect()->route('servidores.show', $servidor)->with('status', 'Servidor criado com sucesso.');
    }

    public function show(Servidor $servidor): View
    {
        $this->authorizeAccess($servidor);

        $servidor->load(['empresa', 'listas', 'allowedIps']);

        $listasDisponiveis = Lista::where('status', 'active')
            ->where(function ($query) use ($servidor) {
                $query->whereNull('empresa_id')->orWhere('empresa_id', $servidor->empresa_id);
            })
            ->whereNotIn('id', $servidor->listas->pluck('id'))
            ->orderBy('nome')
            ->get();

        return view('servidores.show', compact('servidor', 'listasDisponiveis'));
    }

    public function edit(Servidor $servidor): View
    {
        $this->authorizeAccess($servidor);

        $user = Auth::user();
        $empresas = $user->isAdmin() ? Empresa::orderBy('nome')->get() : collect([$user->empresa])->filter();

        return view('servidores.form', compact('servidor', 'empresas'));
    }

    public function update(Request $request, Servidor $servidor): RedirectResponse
    {
        $this->authorizeAccess($servidor);

        $user = Auth::user();
        $data = $this->validated($request, $user);

        if ($user->isCliente()) {
            $data['empresa_id'] = $user->empresa_id;
        }

        $servidor->update($data);

        return redirect()->route('servidores.show', $servidor)->with('status', 'Servidor atualizado com sucesso.');
    }

    public function destroy(Servidor $servidor): RedirectResponse
    {
        $this->authorizeAccess($servidor);

        $servidor->delete();

        return redirect()->route('servidores.index')->with('status', 'Servidor removido.');
    }

    public function attachLista(Servidor $servidor, Lista $lista): RedirectResponse
    {
        $this->authorizeAccess($servidor);

        if ($lista->empresa_id !== null && $lista->empresa_id !== $servidor->empresa_id) {
            abort(403, 'Esta lista não está disponível para esta empresa.');
        }

        $servidor->listas()->syncWithoutDetaching([$lista->id]);

        return back()->with('status', "Lista \"{$lista->nome}\" adicionada ao servidor.");
    }

    public function detachLista(Servidor $servidor, Lista $lista): RedirectResponse
    {
        $this->authorizeAccess($servidor);

        $servidor->listas()->detach($lista->id);

        return back()->with('status', "Lista \"{$lista->nome}\" removida do servidor.");
    }

    public function toggleIpRestriction(Servidor $servidor): RedirectResponse
    {
        $servidor->update(['ip_restriction_enabled' => ! $servidor->ip_restriction_enabled]);

        return back()->with('status', $servidor->ip_restriction_enabled
            ? 'Restrição de IP ativada.'
            : 'Restrição de IP desativada — qualquer IP com o token válido pode sincronizar.');
    }

    public function addAllowedIp(Request $request, Servidor $servidor): RedirectResponse
    {
        $data = $request->validate([
            'ip_cidr' => ['required', 'string', 'max:64'],
        ]);

        $value = trim($data['ip_cidr']);

        if (! $this->validIpOrCidr($value)) {
            return back()->withErrors(['ip_cidr' => 'IP ou CIDR inválido.']);
        }

        $servidor->allowedIps()->firstOrCreate(['ip_cidr' => $value], ['status' => 'active']);

        return back()->with('status', 'IP adicionado à lista de permitidos.');
    }

    public function removeAllowedIp(Servidor $servidor, \App\Models\ServerAllowedIp $ip): RedirectResponse
    {
        if ($ip->servidor_id !== $servidor->id) {
            abort(404);
        }

        $ip->delete();

        return back()->with('status', 'IP removido da lista de permitidos.');
    }

    private function validIpOrCidr(string $value): bool
    {
        if (str_contains($value, '/')) {
            [$ip, $mask] = array_pad(explode('/', $value, 2), 2, null);

            return filter_var($ip, FILTER_VALIDATE_IP) !== false && is_numeric($mask) && (int) $mask >= 0 && (int) $mask <= 128;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    private function authorizeAccess(Servidor $servidor): void
    {
        $user = Auth::user();

        if ($user->isCliente() && $servidor->empresa_id !== $user->empresa_id) {
            abort(403);
        }
    }

    private function licencaBlocker(?Empresa $empresa): ?string
    {
        if (! $empresa) {
            return 'Sua empresa não está configurada corretamente. Fale com o administrador.';
        }

        $capacidade = $empresa->licencas()
            ->where('status', 'active')
            ->whereDate('starts_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', now());
            })
            ->sum('max_servidores');

        if ($capacidade === 0) {
            return 'Sua empresa não possui licença ativa. Fale com o administrador para liberar o cadastro de servidores.';
        }

        $atual = $empresa->servidores()->count();

        if ($atual >= $capacidade) {
            return "Limite de servidores da licença atingido ({$atual}/{$capacidade}). Fale com o administrador para ampliar.";
        }

        return null;
    }

    private function validated(Request $request, $user): array
    {
        $rules = [
            'nome' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ];

        if ($user->isAdmin()) {
            $rules['empresa_id'] = ['required', 'exists:empresas,id'];
        }

        return $request->validate($rules);
    }
}
