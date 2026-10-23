@extends('layouts.app')

@section('title', auth()->user()->isAdmin() ? 'Endpoints RPZ' : 'Servidores')

@section('content')
    @if (auth()->user()->isAdmin())
        @include('servidores.partials.admin-index')
    @else
    <div class="page-heading page-heading-compact">
        <div>
            <h1>{{ auth()->user()->isAdmin() ? 'Endpoints RPZ' : 'Servidores' }}</h1>
            <p>{{ $servidores->total() }} cadastrados • {{ $servidoresAtivos }} ativos</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('servidores.create') }}" class="button button-primary"><x-ui-icon name="plus" /> {{ auth()->user()->isAdmin() ? 'Novo endpoint' : 'Novo servidor' }}</a>
        </div>
    </div>

    <div class="panel @if(auth()->user()->isAdmin()) admin-endpoints-page @else client-servers-page @endif">
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
                            @if (auth()->user()->isAdmin())<th>Empresa</th>@else<th>Tipo</th>@endif
                            <th>{{ auth()->user()->isAdmin() ? 'Fontes habilitadas' : 'Listas' }}</th>
                            <th>{{ auth()->user()->isAdmin() ? 'Última consulta' : 'Última solicitação RPZ' }}</th>
                            <th>Status</th>
                            <th class="table-actions-column">{{ auth()->user()->isAdmin() ? '' : 'Ações' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($servidores as $servidor)
                            @php $dias = $servidor->diasSemSincronizar(); @endphp
                            <tr>
                                <td><a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link">@unless(auth()->user()->isAdmin())<x-ui-icon name="server" size="18" class="client-server-icon" />@endunless{{ $servidor->nome }}</a></td>
                                @if (auth()->user()->isAdmin())<td>{{ $servidor->empresa->nome }}</td>@else<td><span class="server-icon-label"><x-ui-icon name="server" class="server-muted-icon" />{{ $servidor->tipo_dns === 'mikrotik' ? 'MikroTik (Adlist)' : 'Unbound (RPZ)' }}</span></td>@endif
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
                                        <span class="server-icon-label"><x-ui-icon name="clock" class="server-muted-icon" />{{ \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if (auth()->user()->isAdmin())
                                        @php
                                            $estadoLabel = $dias === null ? 'Sem consulta' : ($dias >= 2 ? 'Atenção' : 'Normal');
                                            $estadoClasse = $dias === null ? 'is-muted' : ($dias >= 2 ? 'is-warning' : 'is-active');
                                        @endphp
                                        <div class="endpoint-status-stack">
                                            <span class="status-pill server-status-pill {{ $estadoClasse }}"><x-ui-icon name="dot" size="14" />{{ $estadoLabel }}</span>
                                            <small>{{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }} no painel</small>
                                            @if ($servidor->ips_pendentes_count > 0)
                                                <span class="status-pill is-warning">IP aguardando liberação</span>
                                            @endif
                                            @if ($servidor->tipo_dns !== 'mikrotik' && $servidor->unboundVersionAfetada())
                                                <span class="status-pill is-warning" title="Unbound {{ $servidor->unboundVersion() }} requer revisão de versão — confira consultas e logs do DNS">Unbound desatualizado</span>
                                            @endif
                                        </div>
                                    @else
                                        @php
                                            $estadoLabel = $dias === null ? 'Sem solicitação RPZ' : ($dias >= 2 ? 'Atenção' : 'Em dia');
                                            $estadoClasse = $dias === null ? 'is-muted' : ($dias >= 2 ? 'is-warning' : 'is-active');
                                        @endphp
                                        <div class="endpoint-status-stack">
                                            <span class="status-pill server-status-pill {{ $estadoClasse }}"><x-ui-icon name="dot" size="14" />{{ $estadoLabel }}</span>
                                            @if ($servidor->tipo_dns !== 'mikrotik' && $servidor->unboundVersionAfetada())
                                                <span class="status-pill is-warning" title="Atualize o Unbound para 1.26.1 ou mais recente — ver detalhes do servidor">Precisa atualizar</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="client-server-actions">
                                    @unless(auth()->user()->isAdmin())
                                        <a href="{{ route('servidores.edit', $servidor) }}" class="button button-secondary"><x-ui-icon name="pencil" />Editar</a>
                                    @endunless
                                    <x-actions-menu label="Ações do {{ auth()->user()->isAdmin() ? 'endpoint' : 'servidor' }} {{ $servidor->nome }}">
                                        @if (auth()->user()->isAdmin())
                                            <a href="{{ route('servidores.show', $servidor) }}" class="actions-menu-item">Ver detalhes</a>
                                        @endif
                                        @if(auth()->user()->isAdmin())
                                            <a href="{{ route('servidores.edit', $servidor) }}" class="actions-menu-item">Editar</a>
                                        @else
                                            <a href="{{ route('servidores.show', $servidor) }}" class="actions-menu-item">Ver detalhes</a>
                                        @endif
                                        <div class="actions-menu-divider"></div>
                                        <form action="{{ route('servidores.destroy', $servidor) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="actions-menu-item actions-menu-item-danger" onclick="return confirm('Remover {{ auth()->user()->isAdmin() ? 'endpoint' : 'servidor' }}?')">Remover</button>
                                        </form>
                                    </x-actions-menu>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $servidores->links('pagination.compact') }}
    @endif
@endsection
