<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        return view('profile.show');
    }

    public function editPassword(): View
    {
        return view('profile.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
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
            'current_password.current_password' => 'A senha atual informada está incorreta.',
            'password.regex' => 'A senha deve ter maiúscula, minúscula, número e caractere especial.',
        ]);

        $user = Auth::user();
        $user->update(['password' => Hash::make($request->input('password'))]);

        return redirect()->route('profile.password')->with('status', 'Senha atualizada com sucesso.');
    }
}
