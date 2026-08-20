@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Central de operações</div>
            <h1>Visão geral</h1>
            <p>Empresas, servidores, listas e domínios bloqueados geridos pelo painel.</p>
        </div>
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon cyan"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M6 21V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v16"/><path d="M18 21V9a1 1 0 0 0-1-1h-3"/><path d="M9 7h1"/><path d="M9 11h1"/><path d="M9 15h1"/></svg></div>
                <span class="metric-state">total</span>
            </div>
            <div class="metric-value">{{ $totalEmpresas }}</div>
            <div class="metric-label">Clientes atendidos</div>
            <div class="metric-footer"><span>Empresas ativas</span></div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon violet"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01"/><path d="M7 16.5h.01"/></svg></div>
                <span class="metric-state">total</span>
            </div>
            <div class="metric-value">{{ $totalServidores }}</div>
            <div class="metric-label">Servidores</div>
            <div class="metric-footer"><span>Servidores Unbound ativos</span></div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon amber"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/></svg></div>
                <span class="metric-state">total</span>
            </div>
            <div class="metric-value">{{ $totalListas }}</div>
            <div class="metric-label">Listas de bloqueio</div>
            <div class="metric-footer"><span>Listas ativas</span></div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon green"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m5.5 5.5 13 13"/></svg></div>
                <span class="metric-state">total</span>
            </div>
            <div class="metric-value">{{ $totalDominiosAtivos }}</div>
            <div class="metric-label">Domínios bloqueados</div>
            <div class="metric-footer"><span>{{ $totalDominios }} cadastrados no total ({{ $totalDominios - $totalDominiosAtivos }} inativos)</span></div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header"><h2>Domínios por lista</h2></div>
            @if ($listas->isEmpty())
                <div class="empty-state"><span>Nenhuma lista cadastrada ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Lista</th>
                                <th>Empresa</th>
                                <th>Ativos</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($listas as $lista)
                                <tr>
                                    <td><a href="{{ route('listas.show', $lista) }}" class="table-primary-link">{{ $lista->nome }}</a></td>
                                    <td>{{ $lista->empresa?->nome ?? 'Catálogo (todas)' }}</td>
                                    <td class="table-mono">{{ $lista->dominios_ativos_count }}</td>
                                    <td class="table-mono">{{ $lista->dominios_count }}</td>
                                    <td>
                                        <span class="status-pill @if($lista->status === 'active') is-active @else is-inactive @endif">
                                            {{ $lista->status === 'active' ? 'Ativa' : 'Inativa' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-header"><h2>Últimas sincronizações</h2></div>
            @if ($servidores->isEmpty())
                <div class="empty-state"><span>Nenhum servidor cadastrado ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Servidor</th>
                                <th>Última sync</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($servidores as $servidor)
                                <tr>
                                    <td>
                                        <a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link">{{ $servidor->nome }}</a>
                                        <span class="table-secondary-text">{{ $servidor->empresa->nome }}</span>
                                    </td>
                                    <td class="table-mono">
                                        @if ($servidor->last_synced_at)
                                            {{ $servidor->last_synced_at->diffForHumans() }}
                                        @else
                                            nunca
                                        @endif
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
