@extends('layouts.app')

@section('title', 'Meu perfil')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Conta</div>
            <h1>Meu perfil</h1>
            <p>Dados da sua conta no painel.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('profile.password') }}" class="button button-secondary">Alterar senha</a>
        </div>
    </div>

    <div class="details-grid">
        <div class="panel details-card-wide">
            <div class="panel-header"><h2>Dados da conta</h2></div>
            <dl class="details-list">
                <div><dt>Nome</dt><dd>{{ auth()->user()->name }}</dd></div>
                <div><dt>E-mail de acesso</dt><dd>{{ auth()->user()->email }}</dd></div>
                <div><dt>Perfil</dt><dd>{{ auth()->user()->isAdmin() ? 'Administrador' : 'Cliente' }}</dd></div>
                @if (auth()->user()->empresa)
                    <div><dt>Empresa</dt><dd><a href="{{ route('empresas.show', auth()->user()->empresa) }}" class="inline-link">{{ auth()->user()->empresa->nome }}</a></dd></div>
                @endif
                <div><dt>Conta criada em</dt><dd>{{ auth()->user()->created_at->format('d/m/Y H:i') }}</dd></div>
                <div><dt>Última atualização</dt><dd>{{ auth()->user()->updated_at->diffForHumans() }}</dd></div>
            </dl>
        </div>
    </div>
@endsection
