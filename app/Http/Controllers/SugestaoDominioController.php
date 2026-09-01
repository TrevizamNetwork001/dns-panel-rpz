<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lista;
use App\Models\SugestaoDominio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SugestaoDominioController extends Controller
{
    private const DOMAIN_REGEX = '/^(?!-)[a-z0-9-]{1,63}(?<!-)(\\.[a-z0-9-]{1,63})*\\.[a-z]{2,63}$/i';

    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = SugestaoDominio::with(['empresa', 'lista', 'criadoPor'])->orderByDesc('id');

        if ($user->isCliente()) {
            $query->where('empresa_id', $user->empresa_id);
        }

        $statusFiltro = $request->query('status');
        if (in_array($statusFiltro, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $statusFiltro);
        } else {
            $statusFiltro = null;
        }

        $busca = trim((string) $request->query('q', ''));
        if ($busca !== '') {
            $query->where('dominio', 'like', "%{$busca}%");
        }

        $sugestoes = $query->paginate(20)->withQueryString();

        $listasParaAprovar = $user->isAdmin()
            ? Lista::where('status', 'active')->orderBy('nome')->get()
            : collect();

        return view('sugestoes.index', compact('sugestoes', 'listasParaAprovar', 'statusFiltro', 'busca'));
    }

    public function create(): View
    {
        if (Auth::user()->isAdmin()) {
            abort(403, 'Sugestão de domínio é uma ação do cliente.');
        }

        return view('sugestoes.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isAdmin() || ! $user->empresa_id) {
            abort(403, 'Apenas usuários vinculados a uma empresa podem sugerir domínios.');
        }

        $data = $request->validate([
            'dominio' => ['required', 'string', 'max:255'],
            'motivo' => ['nullable', 'string', 'max:1000'],
        ]);

        $normalized = strtolower(trim($data['dominio']));
        $normalized = rtrim($normalized, '.');

        if ($normalized === '' || ! preg_match(self::DOMAIN_REGEX, $normalized)) {
            return back()->withErrors(['dominio' => 'Domínio inválido.'])->withInput();
        }

        $sugestao = SugestaoDominio::create([
            'empresa_id' => $user->empresa_id,
            'dominio' => $normalized,
            'motivo' => $data['motivo'] ?? null,
            'status' => 'pending',
            'created_by' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        AuditLog::record('sugestao.created', "Domínio {$normalized} sugerido", $user->empresa_id, 'sugestao', $sugestao->id);

        return redirect()->route('sugestoes.index')->with('status', 'Sugestão enviada. O administrador vai revisar.');
    }

    public function aprovar(Request $request, SugestaoDominio $sugestao): RedirectResponse
    {
        $data = $request->validate([
            'lista_id' => ['required', 'exists:listas,id'],
        ]);

        $lista = Lista::findOrFail($data['lista_id']);
        $dominio = $lista->dominios()->where('dominio', $sugestao->dominio)->first();

        if ($dominio === null) {
            $lista->dominios()->create(['dominio' => $sugestao->dominio, 'ativo' => true]);
        } elseif (! $dominio->ativo) {
            $dominio->update(['ativo' => true]);
        }

        $sugestao->update([
            'status' => 'approved',
            'lista_id' => $lista->id,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        AuditLog::record('sugestao.approved', "Sugestão de {$sugestao->dominio} aprovada, adicionada à lista \"{$lista->nome}\"", $sugestao->empresa_id, 'sugestao', $sugestao->id);

        return back()->with('status', "Sugestão aprovada e adicionada à lista \"{$lista->nome}\".");
    }

    public function rejeitar(SugestaoDominio $sugestao): RedirectResponse
    {
        $sugestao->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        AuditLog::record('sugestao.rejected', "Sugestão de {$sugestao->dominio} rejeitada", $sugestao->empresa_id, 'sugestao', $sugestao->id);

        return back()->with('status', 'Sugestão rejeitada.');
    }
}
