<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\User;
use App\Services\TelegramNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function store(Request $request, TelegramNotifier $telegram): RedirectResponse
    {
        $data = $request->validate([
            'empresa_nome' => ['required', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:32'],
            'email_contato' => ['nullable', 'email', 'max:255'],
            'responsavel_nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'password.regex' => 'A senha deve ter maiúscula, minúscula, número e caractere especial.',
        ]);

        [$user, $empresa] = DB::transaction(function () use ($data) {
            $empresa = Empresa::create([
                'nome' => $data['empresa_nome'],
                'documento' => $data['documento'] ?? null,
                'email_contato' => $data['email_contato'] ?? null,
                'status' => 'pending',
            ]);

            $user = User::create([
                'name' => $data['responsavel_nome'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'cliente',
                'empresa_id' => $empresa->id,
            ]);

            return [$user, $empresa];
        });

        Auth::login($user);
        $request->session()->regenerate();

        AuditLog::record('empresa.cadastro_publico', "Empresa \"{$empresa->nome}\" se cadastrou publicamente", $empresa->id);

        $telegram->notifyCadastro($empresa->nome, $user->name, $user->email);

        return redirect()->route('dashboard')
            ->with('status', 'Cadastro recebido! Sua empresa está com aprovação pendente — assim que o administrador ativar sua licença, você poderá cadastrar servidores.');
    }
}
