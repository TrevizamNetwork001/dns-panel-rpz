@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="admin-dashboard">
    <div class="page-heading page-heading-dashboard">
        <div>
            <div class="page-eyebrow">Central de operações</div>
            <h1>Dashboard RPZ</h1>
            <p>Panorama central da distribuição, fontes e endpoints.</p>
        </div>
    </div>

    @php
        $totalDominiosInativos = $totalDominios - $totalDominiosAtivos;
        $totalListasInativas = $totalListasTotal - $totalListasAtivas;
        $totalServidoresInativos = $totalServidoresTotal - $totalServidoresAtivos;
        $percentFontes = $totalListasTotal > 0 ? round(($totalListasAtivas / $totalListasTotal) * 100) : 0;
        $percentEndpoints = $totalServidoresTotal > 0 ? round(($totalServidoresAtivos / $totalServidoresTotal) * 100) : 0;
        $percentEmpresas = $totalEmpresasTotal > 0 ? round(($totalEmpresas / $totalEmpresasTotal) * 100) : 0;
    @endphp

    <div class="metrics-grid">
        <div class="metric-card metric-card-primary">
            <div class="metric-card-header">
                <div class="metric-card-title"><div class="metric-icon cyan"><svg class="ui-icon" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6.5V11c0 4.8 3.2 8.9 8 10 4.8-1.1 8-5.2 8-10V6.5Z"/><path d="M12 7v10"/></svg></div><span class="metric-label">Domínios bloqueados</span></div>
            </div>
            <div class="metric-value-row">
                <div class="metric-value">{{ number_format($totalDominiosAtivos, 0, ',', '.') }}</div>
            </div>
            <div class="metric-footer"><span>{{ number_format($totalDominios, 0, ',', '.') }} cadastrados no total ({{ number_format($totalDominiosInativos, 0, ',', '.') }} inativos)</span></div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-card-title"><div class="metric-icon violet"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg></div><span class="metric-label">{{ $totalListasAtivas === 1 ? 'Fonte ativa' : 'Fontes ativas' }}</span></div>
            </div>
            <div class="metric-value">{{ number_format($totalListasAtivas, 0, ',', '.') }} de {{ number_format($totalListasTotal, 0, ',', '.') }}</div>
            <div class="metric-footer"><span>{{ $percentFontes }}% das fontes habilitadas</span></div>
            <div class="metric-progress"><div class="metric-progress-bar cyan" style="width:{{ $percentFontes }}%"></div></div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-card-title"><div class="metric-icon green"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01"/><path d="M7 16.5h.01"/></svg></div><span class="metric-label">{{ $totalServidoresAtivos === 1 ? 'Endpoint RPZ' : 'Endpoints RPZ' }}</span></div>
            </div>
            <div class="metric-value">{{ number_format($totalServidoresAtivos, 0, ',', '.') }} <span class="metric-value-suffix">{{ $totalServidoresAtivos === 1 ? 'ativo' : 'ativos' }}</span></div>
            <div class="metric-footer">
                <span>
                    @if ($servidoresAtencao > 0)
                        {{ $servidoresAtencao }} em atenção
                    @elseif ($totalServidoresInativos > 0)
                        {{ $totalServidoresInativos }} {{ $totalServidoresInativos === 1 ? 'inativo' : 'inativos' }}
                    @else
                        Todos operando normalmente
                    @endif
                </span>
            </div>
            <div class="metric-progress"><div class="metric-progress-bar green" style="width:{{ $percentEndpoints }}%"></div></div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-card-title"><div class="metric-icon cyan"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M6 21V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v16"/><path d="M18 21V9a1 1 0 0 0-1-1h-3"/><path d="M9 7h1"/><path d="M9 11h1"/><path d="M9 15h1"/></svg></div><span class="metric-label">{{ $totalEmpresas === 1 ? 'Empresa' : 'Empresas' }}</span></div>
            </div>
            <div class="metric-value">{{ number_format($totalEmpresas, 0, ',', '.') }} <span class="metric-value-suffix">{{ $totalEmpresas === 1 ? 'ativa' : 'ativas' }}</span></div>
            <div class="metric-footer"><span>{{ number_format($totalEmpresasTotal - $totalEmpresas, 0, ',', '.') }} {{ ($totalEmpresasTotal - $totalEmpresas) === 1 ? 'inativa' : 'inativas' }}</span></div>
            <div class="metric-progress"><div class="metric-progress-bar cyan" style="width:{{ $percentEmpresas }}%"></div></div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header">
                <h2>Fontes de bloqueio</h2>
                <a href="{{ route('listas.index') }}" class="inline-link">Ver todas as fontes</a>
            </div>
            @if ($listas->isEmpty())
                <div class="empty-state"><span>Nenhuma fonte cadastrada ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table dashboard-sources-table">
                        <colgroup>
                            <col class="source-name-column">
                            <col class="source-type-column">
                            <col class="source-scope-column">
                            <col class="source-count-column">
                            <col class="source-updated-column">
                            <col class="source-status-column">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Escopo</th>
                                <th>Ativos</th>
                                <th>Última atualização</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($listas->take(4) as $lista)
                                @php
                                    $tipo = $lista->isAnatel() ? 'Catálogo' : ($lista->isExterna() ? 'Externa' : ($lista->empresa_id ? 'Própria' : 'Catálogo'));
                                    $tipoClasse = match ($tipo) {
                                        'Externa' => 'is-info',
                                        'Própria' => 'is-active',
                                        default => 'is-violet',
                                    };
                                    $ultimaAtualizacao = $lista->last_sync_at ?? $lista->updated_at;
                                    $fonteAtiva = $lista->status === 'active';
                                @endphp
                                <tr>
                                    <td class="source-name-cell"><a href="{{ route('listas.show', $lista) }}" class="table-primary-link">{{ $lista->nome }}</a></td>
                                    <td><span class="status-pill status-pill-normal-case {{ $tipoClasse }}">{{ $tipo }}</span></td>
                                    <td>{{ $lista->empresa?->nome ?? 'Global' }}</td>
                                    <td class="table-mono">{{ number_format($lista->dominios_ativos_count, 0, ',', '.') }}</td>
                                    <td class="table-mono">{{ \App\Http\Controllers\DashboardController::relativoPt($ultimaAtualizacao) }}</td>
                                    <td>
                                        <span class="status-pill status-pill-normal-case status-pill-dot @if($fonteAtiva) is-active @else is-inactive @endif">
                                            {{ $fonteAtiva ? 'Atualizada' : 'Inativa' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($listas->count() > 4)
                    <p class="dashboard-table-footer">Mostrando 4 de {{ $listas->count() }} fontes</p>
                @endif
            @endif
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2>Estado dos endpoints</h2>
                <a href="{{ route('servidores.index') }}" class="inline-link">Ver todos os endpoints</a>
            </div>
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
                            @foreach ($servidores->take(4) as $servidor)
                                @php
                                    $dias = $servidor->diasSemSincronizar();
                                    $statusLabel = $dias === null ? 'Sem consulta' : ($dias >= 2 ? 'Atenção' : 'Normal');
                                    $statusClasse = $dias === null ? 'is-inactive' : ($dias >= 2 ? 'is-warning' : 'is-active');
                                @endphp
                                <tr>
                                    <td><a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link">{{ $servidor->nome }}</a></td>
                                    <td>{{ $servidor->empresa->nome }}</td>
                                    <td class="table-mono">{{ \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) }}</td>
                                    <td><span class="status-pill status-pill-normal-case status-pill-dot {{ $statusClasse }}">{{ $statusLabel }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($servidores->count() > 4)
                    <p class="dashboard-table-footer">Mostrando 4 de {{ $servidores->count() }} endpoints</p>
                @endif
                <div class="endpoint-summary" aria-label="Resumo do estado dos endpoints">
                    <span><i class="summary-dot is-normal"></i><strong>{{ number_format($resumoEndpoints['normal'], 0, ',', '.') }}</strong> Normal</span>
                    <span><i class="summary-dot is-warning"></i><strong>{{ number_format($resumoEndpoints['atencao'], 0, ',', '.') }}</strong> Atenção</span>
                    <span><i class="summary-dot is-muted"></i><strong>{{ number_format($resumoEndpoints['sem_consulta'], 0, ',', '.') }}</strong> Sem consulta</span>
                </div>
            @endif
        </div>
    </div>

    <div class="panel" style="margin-top:14px">
        <div class="panel-header">
            <h2>Atividade recente</h2>
            <a href="{{ route('auditoria.index') }}" class="inline-link">Ver toda a atividade</a>
        </div>
        @if ($atividadeRecente->isEmpty())
            <div class="empty-state"><span>Nenhum evento registrado ainda.</span></div>
        @else
            <div class="activity-feed">
                @php
                    $iconesPorChave = [
                        'fonte' => '<path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/>',
                        'endpoint' => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01"/><path d="M7 16.5h.01"/>',
                        'sugestao' => '<path d="M12 2.5l2.9 6.06 6.6.95-4.75 4.7 1.1 6.6L12 17.6l-5.85 3.2 1.1-6.6-4.75-4.7 6.6-.95L12 2.5Z"/>',
                        'empresa' => '<path d="M3 21h18"/><path d="M6 21V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v16"/><path d="M18 21V9a1 1 0 0 0-1-1h-3"/>',
                        'usuario' => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.5-6 8-6s8 2 8 6"/>',
                        'licenca' => '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.5-8.5"/><path d="m16.5 6.5 2 2"/>',
                        'seguranca' => '<path d="M12 3 4 6.5V11c0 4.8 3.2 8.9 8 10 4.8-1.1 8-5.2 8-10V6.5Z"/>',
                        'sistema' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.04 1.56V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 9 19.37a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.63 15a1.7 1.7 0 0 0-1.56-1.04H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.63 9a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.63a1.7 1.7 0 0 0 1.04-1.56V3a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15 4.63a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.37 9a1.7 1.7 0 0 0 1.56 1.04H21a2 2 0 1 1 0 4h-.09A1.7 1.7 0 0 0 19.4 15Z"/>',
                    ];
                @endphp
                @foreach ($atividadeRecente as $evento)
                    <div class="activity-feed-item">
                        <div class="activity-feed-icon {{ $evento['cor'] }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">{!! $iconesPorChave[$evento['icone']] ?? $iconesPorChave['sistema'] !!}</svg>
                        </div>
                        <div class="activity-feed-body">
                            <strong>{{ $evento['titulo'] }}</strong>
                            <span>{{ $evento['descricao'] }}</span>
                        </div>
                        <span class="activity-feed-time">{{ \App\Http\Controllers\DashboardController::relativoPt($evento['timestamp']) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
