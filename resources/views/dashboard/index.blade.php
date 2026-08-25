@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-heading page-heading-dashboard">
        <div>
            <div class="page-eyebrow">Central de operações</div>
            <h1>Visão geral</h1>
            <p>Domínios, fontes de bloqueio e endpoints RPZ geridos pelo painel.</p>
        </div>
    </div>

    @php
        $totalDominiosInativos = $totalDominios - $totalDominiosAtivos;
        $totalListasInativas = $totalListasTotal - $totalListasAtivas;
        $totalServidoresInativos = $totalServidoresTotal - $totalServidoresAtivos;
    @endphp

    <div class="metrics-grid">
        <div class="metric-card metric-card-primary">
            <div class="metric-card-header">
                <div class="metric-icon green"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m5.5 5.5 13 13"/></svg></div>
            </div>
            <div class="metric-value">{{ number_format($totalDominiosAtivos, 0, ',', '.') }}</div>
            <div class="metric-label">Domínios bloqueados</div>
            <div class="metric-footer"><span>{{ number_format($totalDominios, 0, ',', '.') }} cadastrados no total ({{ number_format($totalDominiosInativos, 0, ',', '.') }} inativos)</span></div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon amber"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/></svg></div>
            </div>
            <div class="metric-value">{{ number_format($totalListasAtivas, 0, ',', '.') }} de {{ number_format($totalListasTotal, 0, ',', '.') }}</div>
            <div class="metric-label">Fontes ativas</div>
            <div class="metric-footer">
                <span>
                    @if ($totalListasInativas > 0)
                        {{ number_format($totalListasInativas, 0, ',', '.') }} {{ $totalListasInativas === 1 ? 'inativa' : 'inativas' }}
                    @else
                        Todas as fontes cadastradas estão ativas
                    @endif
                </span>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon violet"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01"/><path d="M7 16.5h.01"/></svg></div>
            </div>
            <div class="metric-value">{{ number_format($totalServidoresAtivos, 0, ',', '.') }}</div>
            <div class="metric-label">Endpoints RPZ</div>
            <div class="metric-footer">
                <span>
                    {{ number_format($totalServidoresAtivos, 0, ',', '.') }} {{ $totalServidoresAtivos === 1 ? 'ativo' : 'ativos' }}
                    @if ($servidoresAtencao > 0)
                        &middot; {{ $servidoresAtencao }} sem sincronizar há 2+ dias
                    @elseif ($totalServidoresInativos > 0)
                        &middot; {{ $totalServidoresInativos }} {{ $totalServidoresInativos === 1 ? 'inativo' : 'inativos' }}
                    @endif
                </span>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon cyan"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M6 21V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v16"/><path d="M18 21V9a1 1 0 0 0-1-1h-3"/><path d="M9 7h1"/><path d="M9 11h1"/><path d="M9 15h1"/></svg></div>
            </div>
            <div class="metric-value">{{ number_format($totalEmpresas, 0, ',', '.') }}</div>
            <div class="metric-label">Empresas</div>
            <div class="metric-footer"><span>{{ number_format($totalEmpresas, 0, ',', '.') }} {{ $totalEmpresas === 1 ? 'ativa' : 'ativas' }}</span></div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header"><h2>Fontes de bloqueio</h2></div>
            @if ($listas->isEmpty())
                <div class="empty-state"><span>Nenhuma fonte cadastrada ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fonte</th>
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
                                    <td class="table-mono">{{ number_format($lista->dominios_ativos_count, 0, ',', '.') }}</td>
                                    <td class="table-mono">{{ number_format($lista->dominios_count, 0, ',', '.') }}</td>
                                    <td>
                                        <span class="status-pill status-pill-normal-case @if($lista->status === 'active') is-active @else is-inactive @endif">
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
            <div class="panel-header"><h2>Últimas consultas RPZ</h2></div>
            @if ($servidores->isEmpty())
                <div class="empty-state"><span>Nenhum endpoint cadastrado ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Endpoint</th>
                                <th>Empresa</th>
                                <th>Última consulta</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($servidores as $servidor)
                                @php
                                    $dias = $servidor->diasSemSincronizar();
                                    $statusLabel = $dias === null ? 'Sem consulta' : ($dias >= 2 ? 'Atenção' : 'Normal');
                                    $statusClasse = $dias === null ? 'is-inactive' : ($dias >= 2 ? 'is-warning' : 'is-active');
                                @endphp
                                <tr>
                                    <td><a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link">{{ $servidor->nome }}</a></td>
                                    <td>{{ $servidor->empresa->nome }}</td>
                                    <td class="table-mono">
                                        @if ($dias === null)
                                            nunca
                                        @elseif ($dias === 0)
                                            hoje
                                        @else
                                            há {{ $dias }} {{ $dias === 1 ? 'dia' : 'dias' }}
                                        @endif
                                    </td>
                                    <td><span class="status-pill status-pill-normal-case {{ $statusClasse }}">{{ $statusLabel }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
