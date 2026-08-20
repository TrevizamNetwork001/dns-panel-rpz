<?php

namespace App\Http\Controllers;

use App\Models\Lista;
use App\Models\SugestaoDominio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SugestaoDominioController extends Controller
{
    private const DOMAIN_REGEX = '/^(?!-)[a-z0-9-]{1,63}(?<!-)(\\.[a-z0-9-]{1,63})*\\.[a-z]{2,63}$/i';

    public function index(): View
    {
        $user = Auth::user();

        $query = SugestaoDominio::with(['empresa', 'lista', 'criadoPor'])->orderByDesc('id');

        if ($user->isCliente()) {
            $query->where('empresa_id', $user->empresa_id);
        }

        $sugestoes = $query->paginate(20);

        $listasParaAprovar = $user->isAdmin()
            ? Lista::where('status', 'active')->orderBy('nome')->get()
            : collect();

        return view('sugestoes.index', compact('sugestoes', 'listasParaAprovar'));
    }

    public function create(): View
    {
        return view('sugestoes.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->empresa_id) {
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

        SugestaoDominio::create([
            'empresa_id' => $user->empresa_id,
            'dominio' => $normalized,
            'motivo' => $data['motivo'] ?? null,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        return redirect()->route('sugestoes.index')->with('status', 'Sugestão enviada. O administrador vai revisar.');
    }

    public function aprovar(Request $request, SugestaoDominio $sugestao): RedirectResponse
    {
        $data = $request->validate([
            'lista_id' => ['required', 'exists:listas,id'],
        ]);

        $lista = Lista::findOrFail($data['lista_id']);
        $lista->dominios()->firstOrCreate(['dominio' => $sugestao->dominio], ['ativo' => true]);

        $sugestao->update([
            'status' => 'approved',
            'lista_id' => $lista->id,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('status', "Sugestão aprovada e adicionada à lista \"{$lista->nome}\".");
    }

    public function rejeitar(SugestaoDominio $sugestao): RedirectResponse
    {
        $sugestao->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'Sugestão rejeitada.');
    }
}
