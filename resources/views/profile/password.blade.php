@extends('layouts.app')

@section('title', 'Alterar senha')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Conta</div>
            <h1>Alterar senha</h1>
            <p>{{ auth()->user()->name }} &middot; {{ auth()->user()->email }}</p>
        </div>
    </div>

    <div class="panel form-panel">
        <form action="{{ route('profile.password.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="field-group">
                <label for="current_password">Senha atual</label>
                <input class="form-control" type="password" id="current_password" name="current_password" required>
            </div>

            <div class="field-group">
                <label for="password">Nova senha</label>
                <input class="form-control" type="password" id="password" name="password" required>
                <p style="color:var(--text-muted);font-size:10px;margin-top:6px">Mínimo 8 caracteres, com maiúscula, minúscula, número e caractere especial.</p>
            </div>

            <div class="field-group">
                <label for="password_confirmation">Confirmar nova senha</label>
                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="button button-primary">Salvar nova senha</button>
            </div>
        </form>
    </div>
@endsection
