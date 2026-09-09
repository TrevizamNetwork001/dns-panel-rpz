<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $usuarios = User::with('empresa')->orderBy('name')->paginate(20);

        $passwordReset = $request->session()->pull('admin_user_password_reset');

        return response()->view('usuarios.index', compact('usuarios', 'passwordReset'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function create(Request $request): View
    {
        $usuario = new User(['empresa_id' => $request->integer('empresa_id') ?: null]);
        $empresas = Empresa::orderBy('nome')->get();

        return view('usuarios.form', compact('usuario', 'empresas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $senha = User::generateTemporaryPassword();

        $usuario = User::create([
            ...$data,
            'password' => Hash::make($senha),
        ]);

        AuditLog::record('user.created', "Usuário {$usuario->email} criado (papel {$usuario->role})", $usuario->empresa_id, 'user', $usuario->id);

        return redirect()->route('usuarios.index')
            ->with('status', "Usuário criado. Senha temporária (mostrada só agora): {$senha}");
    }

    public function edit(User $usuario): View
    {
        $empresas = Empresa::orderBy('nome')->get();

        return view('usuarios.form', compact('usuario', 'empresas'));
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $data = $this->validated($request, $usuario);

        if ($usuario->isAdmin() && $data['role'] === 'cliente' && User::where('role', 'admin')->count() <= 1) {
            return back()->withErrors(['role' => 'Não é possível rebaixar o último administrador.']);
        }

        $usuario->update($data);

        AuditLog::record('user.updated', "Usuário {$usuario->email} atualizado", $usuario->empresa_id, 'user', $usuario->id);

        return redirect()->route('usuarios.index')->with('status', 'Usuário atualizado.');
    }

    public function destroy(User $usuario): RedirectResponse
    {
        if ($usuario->id === Auth::id()) {
            return back()->withErrors(['usuario' => 'Você não pode remover a própria conta.']);
        }

        if ($usuario->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return back()->withErrors(['usuario' => 'Não é possível remover o último administrador.']);
        }

        AuditLog::record('user.destroyed', "Usuário {$usuario->email} removido", $usuario->empresa_id, 'user', $usuario->id);

        $usuario->delete();

        return redirect()->route('usuarios.index')->with('status', 'Usuário removido.');
    }

    public function resetPassword(User $usuario): RedirectResponse
    {
        $senha = User::generateTemporaryPassword();
        $usuario->update(['password' => Hash::make($senha)]);

        AuditLog::record('user.password_reset', "Senha de {$usuario->email} redefinida pelo admin", $usuario->empresa_id, 'user', $usuario->id);

        return redirect()->route('usuarios.index')
            ->with('status', 'Senha redefinida com sucesso.')
            ->with('admin_user_password_reset', ['password' => $senha, 'user_id' => $usuario->id]);
    }

    private function validated(Request $request, ?User $usuario = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'.($usuario ? ",{$usuario->id}" : '')],
            'role' => ['required', 'in:admin,cliente'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
        ]);

        if ($data['role'] === 'cliente' && ! $data['empresa_id']) {
            throw ValidationException::withMessages([
                'empresa_id' => 'Usuário cliente precisa estar vinculado a uma empresa.',
            ]);
        }

        if ($data['role'] === 'admin') {
            $data['empresa_id'] = null;
        }

        return $data;
    }
}
