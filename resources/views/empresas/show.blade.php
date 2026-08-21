@extends('layouts.app')

@section('title', $empresa->nome)

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Empresa</div>
            <h1>{{ $empresa->nome }}</h1>
            <p>{{ $empresa->documento ?? 'sem documento' }} &middot; {{ $empresa->email_contato ?? 'sem e-mail' }}</p>
        </div>
        <div class="page-actions">
            @if (auth()->user()->isAdmin())<a href="{{ route('empresas.edit', $empresa) }}" class="button button-secondary">Editar</a>@endif
        </div>
    </div>

    <div class="details-grid">
        <div class="panel">
            <div class="panel-header"><h2>Status</h2></div>
            <span class="status-pill @if($empresa->status === 'active') is-active @else is-inactive @endif">
                {{ $empresa->status === 'active' ? 'Ativa' : 'Inativa' }}
            </span>
        </div>

        @if (auth()->user()->isAdmin())
        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Chave de API</h2>
                <span class="status-pill is-info">acesso via X-Api-Key</span>
            </div>
            <p style="color:var(--text-muted);font-size:11px;margin:0 0 10px">Autentica chamadas à API em nome desta empresa (equivalente a um usuário cliente — nunca acesso de admin). Header: <code>X-Api-Key: {chave}</code>.</p>
            <code style="display:block;word-break:break-all;background:#080d17;border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:12px">{{ $empresa->api_key ?? 'ainda não gerada' }}</code>
            <form action="{{ route('empresas.regenerate-api-key', $empresa) }}" method="POST" style="margin-top:10px">
                @csrf
                <button type="submit" class="button button-secondary" onclick="return confirm('Regenerar a chave? A chave antiga para de funcionar imediatamente.')">Regenerar chave</button>
            </form>
        </div>
        @endif

        <div class="panel details-card-wide">
            <div class="panel-header"><h2>Servidores</h2></div>
            @if ($empresa->servidores->isEmpty())
                <div class="empty-state"><span>Nenhum servidor cadastrado.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <tbody>
                            @foreach ($empresa->servidores as $servidor)
                                <tr><td><a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link">{{ $servidor->nome }}</a></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel details-card-wide">
            <div class="panel-header"><h2>Listas</h2></div>
            @if ($empresa->listas->isEmpty())
                <div class="empty-state"><span>Nenhuma lista cadastrada.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <tbody>
                            @foreach ($empresa->listas as $lista)
                                <tr><td><a href="{{ route('listas.show', $lista) }}" class="table-primary-link">{{ $lista->nome }}</a></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if (auth()->user()->isAdmin())
        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Usuários</h2>
                <a href="{{ route('usuarios.create', ['empresa_id' => $empresa->id]) }}" class="button button-primary">+ Criar acesso</a>
            </div>
            @if ($empresa->users->isEmpty())
                <div class="empty-state"><span>Nenhum usuário com acesso a esta empresa ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Nome</th><th>E-mail</th><th class="table-actions-column"></th></tr></thead>
                        <tbody>
                            @foreach ($empresa->users as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td class="table-mono">{{ $user->email }}</td>
                                    <td><a href="{{ route('usuarios.edit', $user) }}" class="table-action-link">Editar</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif

        <div class="panel details-card-wide">
            <div class="panel-header"><h2>Licenças</h2></div>
            @if ($empresa->licencas->isEmpty())
                <div class="empty-state"><span>Nenhuma licença cadastrada.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Início</th><th>Expiração</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($empresa->licencas as $licenca)
                                <tr>
                                    <td>{{ $licenca->starts_at->format('d/m/Y') }}</td>
                                    <td>{{ optional($licenca->expires_at)->format('d/m/Y') ?? 'sem expiração' }}</td>
                                    <td>
                                        <span class="status-pill @if($licenca->status === 'active') is-active @else is-inactive @endif">
                                            {{ $licenca->status }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
