@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">{{ $empresa->nome ?? 'Minha empresa' }}</div>
            <h1>Visão geral</h1>
            <p>Servidores, listas e domínios bloqueados da sua empresa.</p>
        </div>
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon violet">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01"/><path d="M7 16.5h.01"/></svg>
                </div>
                <span class="metric-state">total</span>
            </div>
            <div class="metric-value">{{ $totalServidores }}</div>
            <div class="metric-label">Servidores</div>
            <div class="metric-footer">
                <span>{{ $totalServidores }}/{{ $capacidadeLicenca }} usados da licença</span>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon amber">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/></svg>
                </div>
                <span class="metric-state">total</span>
            </div>
            <div class="metric-value">{{ $totalListas }}</div>
            <div class="metric-label">Listas disponíveis</div>
            <div class="metric-footer"><span>Catálogo + próprias</span></div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon green">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m5.5 5.5 13 13"/></svg>
                </div>
                <span class="metric-state">total</span>
            </div>
            <div class="metric-value">{{ $totalDominiosAtivos }}</div>
            <div class="metric-label">Domínios bloqueados</div>
            <div class="metric-footer"><span>Nos seus servidores</span></div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon cyan">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M6 21V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v16"/><path d="M18 21V9a1 1 0 0 0-1-1h-3"/></svg>
                </div>
                <span class="metric-state">licença</span>
            </div>
            <div class="metric-value">{{ $capacidadeLicenca }}</div>
            <div class="metric-label">Servidores contratados</div>
            <div class="metric-footer"><span>Limite atual da sua licença</span></div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header"><h2>Listas disponíveis</h2></div>
            @if ($listas->isEmpty())
                <div class="empty-state"><span>Nenhuma lista disponível ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Lista</th><th>Domínios ativos</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($listas as $lista)
                                <tr>
                                    <td><a href="{{ route('listas.show', $lista) }}" class="table-primary-link">{{ $lista->nome }}</a></td>
                                    <td class="table-mono">{{ $lista->dominios_ativos_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-header"><h2>Meus servidores</h2></div>
            @if ($servidores->isEmpty())
                <div class="empty-state"><span>Nenhum servidor cadastrado ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Servidor</th><th>Última sync</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($servidores as $servidor)
                                <tr>
                                    <td><a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link">{{ $servidor->nome }}</a></td>
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

        @if ($sugestoesRecentes->isNotEmpty())
        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Minhas sugestões de domínio</h2>
                <a href="{{ route('sugestoes.index') }}" class="table-action-link">Ver todas</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Domínio</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($sugestoesRecentes as $sugestao)
                            <tr>
                                <td class="table-mono">{{ $sugestao->dominio }}</td>
                                <td>
                                    @if ($sugestao->status === 'pending')
                                        <span class="status-pill is-inactive">Pendente</span>
                                    @elseif ($sugestao->status === 'approved')
                                        <span class="status-pill is-active">Aprovada &mdash; {{ $sugestao->lista->nome ?? '-' }}</span>
                                    @else
                                        <span class="status-pill is-inactive">Rejeitada</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
@endsection
