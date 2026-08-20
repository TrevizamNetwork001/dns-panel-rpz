@extends('layouts.app')

@section('title', 'Meu perfil')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Conta</div>
            <h1>Meu perfil</h1>
            <p>Consulte seus dados e personalize sua identificação na plataforma.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('profile.password') }}" class="button button-secondary">Alterar senha</a>
        </div>
    </div>

    <div class="profile-layout">
        <div class="profile-sidebar">
            <div class="panel">
                <div class="profile-summary-card">
                    <span class="user-avatar user-avatar-large @if(auth()->user()->avatar) has-symbol @endif">
                        @if (auth()->user()->avatarSymbol())
                            <span class="user-avatar-symbol">{{ auth()->user()->avatarSymbol() }}</span>
                        @else
                            <span class="user-avatar-initials">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
                        @endif
                    </span>
                    <div>
                        <h2>{{ auth()->user()->name }}</h2>
                        <span class="status-pill is-active">{{ auth()->user()->isAdmin() ? 'Administrador' : 'Cliente' }}</span>
                    </div>
                </div>
            </div>

            <div class="panel profile-account-card">
                <div class="panel-header"><h2>Dados da conta</h2></div>
                <dl class="details-list">
                    <div><dt>Nome</dt><dd class="profile-account-value">{{ auth()->user()->name }}</dd></div>
                    <div><dt>E-mail de acesso</dt><dd class="profile-account-value">{{ auth()->user()->email }}</dd></div>
                    <div><dt>Perfil</dt><dd>{{ auth()->user()->isAdmin() ? 'Administrador' : 'Cliente' }}</dd></div>
                    @if (auth()->user()->empresa)
                        <div><dt>Empresa</dt><dd><a href="{{ route('empresas.show', auth()->user()->empresa) }}" class="inline-link">{{ auth()->user()->empresa->nome }}</a></dd></div>
                    @endif
                    <div><dt>Conta criada em</dt><dd>{{ auth()->user()->created_at->format('d/m/Y H:i') }}</dd></div>
                    <div><dt>Última atualização</dt><dd>{{ auth()->user()->updated_at->diffForHumans() }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="panel">
            <div class="page-eyebrow">Personalização</div>
            <div class="panel-header"><h2>Escolha seu avatar</h2></div>
            <form action="{{ route('profile.avatar.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="avatar-picker">
                    <label class="avatar-option">
                        <input type="radio" name="avatar" value="" @checked(!auth()->user()->avatar) onchange="this.form.submit()">
                        <span class="user-avatar" style="width:44px;height:44px;border-radius:12px;display:grid;place-items:center">
                            <span class="user-avatar-initials">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
                        </span>
                        <span class="avatar-option-label">Inicial do nome</span>
                    </label>
                    @foreach (\App\Models\User::avatarOptions() as $key => $option)
                        <label class="avatar-option">
                            <input type="radio" name="avatar" value="{{ $key }}" @checked(auth()->user()->avatar === $key) onchange="this.form.submit()">
                            <span class="user-avatar has-symbol" style="width:44px;height:44px;border-radius:12px;display:grid;place-items:center">
                                <span class="user-avatar-symbol">{{ $option['symbol'] }}</span>
                            </span>
                            <span class="avatar-option-label">{{ $option['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </form>
        </div>
    </div>
@endsection
