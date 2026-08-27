@extends('layouts.app')

@section('title', $empresa->nome)

@section('content')
    @php
        $isAdmin = auth()->user()->isAdmin();
        $servidoresUtilizados = $empresa->servidores->count();
    @endphp

    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Empresa</div>
            <h1>{{ $empresa->nome }}</h1>
            @if ($isAdmin)
                <p>{{ $empresa->documento ?? 'sem documento' }} &middot; {{ $empresa->email_contato ?? 'sem e-mail' }}</p>
            @else
                <p style="display:flex;gap:6px 18px;flex-wrap:wrap;align-items:center">
                    <span><strong>Documento</strong> <span class="table-mono">{{ $empresa->documentoFormatado() ?? 'sem documento' }}</span></span>
                    <span style="overflow-wrap:anywhere"><strong>E-mail de contato</strong> {{ $empresa->email_contato ?? 'sem e-mail' }}</span>
                    <span class="status-pill {{ $empresa->status === 'active' ? 'is-active' : 'is-inactive' }}">
                        {{ $empresa->status === 'active' ? 'Ativa' : 'Inativa' }}
                    </span>
                </p>
            @endif
        </div>
        <div class="page-actions">
            @if (auth()->user()->isAdmin())<a href="{{ route('empresas.edit', $empresa) }}" class="button button-secondary">Editar</a>@endif
        </div>
    </div>

    <div class="details-grid">
        @if ($isAdmin)
        <div class="panel">
            <div class="panel-header"><h2>Status</h2></div>
            <span class="status-pill @if($empresa->status === 'active') is-active @else is-inactive @endif">
                {{ $empresa->status === 'active' ? 'Ativa' : 'Inativa' }}
            </span>
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
            <div class="panel-header"><h2>{{ ! $isAdmin && $empresa->licencas->count() === 1 ? 'Licença' : 'Licenças' }}</h2></div>
            @if ($empresa->licencas->isEmpty())
                <div class="empty-state"><span>Nenhuma licença cadastrada.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Início</th><th>Expiração</th><th>Status</th>
                                @if (! $isAdmin)<th>Validade</th><th>Uso</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($empresa->licencas as $licenca)
                                @php
                                    $statusLabel = match ($licenca->status) {
                                        'active' => 'Ativa',
                                        'inactive' => 'Inativa',
                                        'expired' => 'Expirada',
                                        default => ucfirst($licenca->status),
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $licenca->starts_at->format('d/m/Y') }}</td>
                                    <td>{{ optional($licenca->expires_at)->format('d/m/Y') ?? 'sem expiração' }}</td>
                                    <td>
                                        <span class="status-pill @if($licenca->status === 'active') is-active @else is-inactive @endif">
                                            {{ $isAdmin ? $licenca->status : $statusLabel }}
                                        </span>
                                    </td>
                                    @if (! $isAdmin)
                                        <td>{{ $licenca->expirationSummary() ?? 'Sem expiração' }}</td>
                                        <td>
                                            {{ $servidoresUtilizados.' de '.$licenca->max_servidores.' '.($licenca->max_servidores === 1 ? 'servidor utilizado' : 'servidores utilizados') }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
