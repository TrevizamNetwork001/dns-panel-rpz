@extends('layouts.app')

@section('title', auth()->user()->isAdmin() ? 'Endpoints RPZ' : 'Servidores')

@section('content')
    <div class="page-heading page-heading-compact">
        <div>
            <h1>{{ auth()->user()->isAdmin() ? 'Endpoints RPZ' : 'Servidores' }}</h1>
            <p>{{ $servidores->total() }} cadastrados · {{ $servidoresAtivos }} ativos</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('servidores.create') }}" class="button button-primary">+ {{ auth()->user()->isAdmin() ? 'Novo endpoint' : 'Novo servidor' }}</a>
        </div>
    </div>

    <div class="panel @if(auth()->user()->isAdmin()) admin-endpoints-page @endif">
        @if ($servidores->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">+</div>
                <div>
                    <strong>{{ auth()->user()->isAdmin() ? 'Nenhum endpoint cadastrado' : 'Nenhum servidor cadastrado' }}</strong>
                    <span>{{ auth()->user()->isAdmin() ? 'Cadastre o primeiro endpoint RPZ para gerar o token do zonefile.' : 'Cadastre seu primeiro servidor para começar.' }}</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table @if(auth()->user()->isAdmin()) admin-endpoints-table @endif">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            @if (auth()->user()->isAdmin())<th>Empresa</th>@endif
                            <th>{{ auth()->user()->isAdmin() ? 'Fontes habilitadas' : 'Listas' }}</th>
                            <th>{{ auth()->user()->isAdmin() ? 'Última consulta' : 'Última sincronização' }}</th>
                            <th>Status</th>
                            <th class="table-actions-column"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($servidores as $servidor)
                            @php $dias = $servidor->diasSemSincronizar(); @endphp
                            <tr>
                                <td><a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link">{{ $servidor->nome }}</a></td>
                                @if (auth()->user()->isAdmin())<td>{{ $servidor->empresa->nome }}</td>@endif
                                <td>
                                    @if ($servidor->listas->isEmpty())
                                        <a href="{{ route('servidores.show', $servidor) }}" class="inline-link" style="color:var(--danger)">nenhuma — escolher</a>
                                    @else
                                        <a href="{{ route('servidores.show', $servidor) }}" class="inline-link" title="{{ $servidor->listas->pluck('nome')->join(', ') }}">{{ $servidor->listas->count() }} {{ auth()->user()->isAdmin() ? ($servidor->listas->count() === 1 ? 'fonte' : 'fontes') : ($servidor->listas->count() === 1 ? 'lista' : 'listas') }}</a>
                                    @endif
                                </td>
                                <td class="table-mono">
                                    @if (auth()->user()->isAdmin())
                                        {{ \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) }}
                                    @else
                                        {{ \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) }}
                                    @endif
                                </td>
                                <td>
                                    @if (auth()->user()->isAdmin())
                                        @php
                                            $estadoLabel = $dias === null ? 'Sem consulta' : ($dias >= 2 ? 'Atenção' : 'Normal');
                                            $estadoClasse = $dias === null ? 'is-muted' : ($dias >= 2 ? 'is-warning' : 'is-active');
                                        @endphp
                                        <div class="endpoint-status-stack">
                                            <span class="status-pill {{ $estadoClasse }}">{{ $estadoLabel }}</span>
                                            <small>{{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }} no painel</small>
                                        </div>
                                    @else
                                        @php
                                            $estadoLabel = $dias === null ? 'Sem sincronização' : ($dias >= 2 ? 'Atenção' : 'Normal');
                                            $estadoClasse = $dias === null ? 'is-muted' : ($dias >= 2 ? 'is-warning' : 'is-active');
                                        @endphp
                                        <span class="status-pill {{ $estadoClasse }}">{{ $estadoLabel }}</span>
                                    @endif
                                </td>
                                <td>
                                    <x-actions-menu label="Ações do {{ auth()->user()->isAdmin() ? 'endpoint' : 'servidor' }} {{ $servidor->nome }}">
                                        @if (auth()->user()->isAdmin())
                                            <a href="{{ route('servidores.show', $servidor) }}" class="actions-menu-item">Ver detalhes</a>
                                        @endif
                                        <a href="{{ route('servidores.edit', $servidor) }}" class="actions-menu-item">Editar</a>
                                        <div class="actions-menu-divider"></div>
                                        <form action="{{ route('servidores.destroy', $servidor) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="actions-menu-item actions-menu-item-danger" onclick="return confirm('Remover {{ auth()->user()->isAdmin() ? 'endpoint' : 'servidor' }}?')">Remover</button>
                                        </form>
                                    </x-actions-menu>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $servidores->links() }}
@endsection
