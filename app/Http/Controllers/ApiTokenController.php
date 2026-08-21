<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function index(): View
    {
        $tokens = Auth::user()->tokens()->orderByDesc('id')->get();

        return view('profile.tokens', compact('tokens'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $user = Auth::user();
        $token = $user->createToken($data['name']);

        AuditLog::record('api.token_created', "Token de API \"{$data['name']}\" criado por {$user->email}", $user->empresa_id, 'user', $user->id);

        return redirect()->route('profile.tokens')
            ->with('status', 'Token criado. Copie agora — não será mostrado de novo.')
            ->with('novo_token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $user = Auth::user();
        $token = $user->tokens()->where('id', $tokenId)->first();

        if (! $token) {
            abort(404);
        }

        $nome = $token->name;
        $token->delete();

        AuditLog::record('api.token_revoked', "Token de API \"{$nome}\" revogado por {$user->email}", $user->empresa_id, 'user', $user->id);

        return redirect()->route('profile.tokens')->with('status', 'Token revogado.');
    }
}
