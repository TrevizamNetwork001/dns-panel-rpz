@extends('layouts.app')

@section('title', 'Alterar senha')

@section('content')
@include('profile.partials.styles')
<div class="profile-refined profile-password">
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Conta</div>
            <h1>Alterar senha</h1>
            <p>{{ auth()->user()->name }} &middot; {{ auth()->user()->email }}</p>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Segurança da conta</h2></div>
        <form class="profile-password-form" action="{{ route('profile.password.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="field-group">
                <label for="current_password">Senha atual</label>
                <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
            </div>

            <div class="field-group">
                <label for="password">Nova senha</label>
                <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" aria-describedby="password-help" required>
                <p id="password-help" class="profile-help">Mínimo 8 caracteres, com maiúscula, minúscula, número e caractere especial.</p>
            </div>

            <div class="field-group">
                <label for="password_confirmation">Confirmar nova senha</label>
                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
            </div>

            <div class="form-actions">
                <a href="{{ route('profile.show') }}" class="button button-secondary">Voltar ao perfil</a>
                <button type="submit" class="button button-primary">Salvar nova senha</button>
            </div>
        </form>
    </div>
</div>
@endsection
