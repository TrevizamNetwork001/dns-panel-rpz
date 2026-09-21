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
        @php
            $servidoresDisponiveis = max(0, $capacidadeLicenca - $totalServidores);
            $usoAcimaDoLimite = $capacidadeLicenca > 0 && $totalServidores > $capacidadeLicenca;
        @endphp
        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon violet">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01"/><path d="M7 16.5h.01"/></svg>
                </div>
                <span class="metric-state">total</span>
            </div>
            <div class="metric-value">{{ number_format($totalDominiosAtivos, 0, ',', '.') }}</div>
            <div class="metric-label">Domínios bloqueados</div>
            <div class="metric-footer"><span>Nos seus servidores</span></div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon amber">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/></svg>
                </div>
                <span class="metric-state">total</span>
            </div>
            <div class="metric-value">{{ number_format($totalListas, 0, ',', '.') }}</div>
            <div class="metric-label">Listas disponíveis</div>
            <div class="metric-footer"><span>{{ $totalListas === 1 ? '1 disponível' : number_format($totalListas, 0, ',', '.').' disponíveis' }}</span></div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon green">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01"/><path d="M7 16.5h.01"/></svg>
                </div>
                <span class="metric-state">total</span>
            </div>
            <div class="metric-value">{{ number_format($totalServidores, 0, ',', '.') }}</div>
            <div class="metric-label">{{ $totalServidores === 1 ? 'Servidor em uso' : 'Servidores em uso' }}</div>
            <div class="metric-footer"><span>
                @if ($estadoLicenca)
                    {{ $estadoLicenca }}
                @elseif ($usoAcimaDoLimite)
                    Uso acima do limite da licença ({{ number_format($totalServidores, 0, ',', '.') }} de {{ number_format($capacidadeLicenca, 0, ',', '.') }})
                @else
                    {{ number_format($totalServidores, 0, ',', '.') }} de {{ number_format($capacidadeLicenca, 0, ',', '.') }} {{ $totalServidores === 1 ? 'usado' : 'usados' }} da licença
                @endif
            </span></div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon cyan">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.5-8.5"/><path d="m16.5 6.5 2 2"/><path d="m14 9 2 2"/></svg>
                </div>
                <span class="metric-state">licença</span>
            </div>
            <div class="metric-value">{{ $estadoLicenca ? '—' : number_format($capacidadeLicenca, 0, ',', '.') }}</div>
            <div class="metric-label">{{ $estadoLicenca ?: ($capacidadeLicenca === 1 ? 'Servidor contratado' : 'Servidores contratados') }}</div>
            <div class="metric-footer"><span>
                @if ($estadoLicenca)
                    Consulte o administrador
                @elseif ($usoAcimaDoLimite)
                    Nenhum disponível · limite excedido
                @else
                    {{ number_format($servidoresDisponiveis, 0, ',', '.') }} {{ $servidoresDisponiveis === 1 ? 'disponível' : 'disponíveis' }}
                @endif
            </span></div>
        </div>
    </div>

    @include('dashboard.partials.grafico-atualizacoes', ['pontos' => $graficoAtualizacoes])

    <div class="dashboard-grid dashboard-grid-cliente">
        <div class="panel">
            <div class="panel-header"><h2>Meus servidores</h2></div>
            @if ($servidores->isEmpty())
                <div class="empty-state"><span>Nenhum servidor cadastrado ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Servidor</th><th>Última sincronização</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($servidores as $servidor)
                                <tr>
                                    <td><a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link">{{ $servidor->nome }}</a></td>
                                    @php
                                        $dias = $servidor->diasSemSincronizar();
                                        $estadoLabel = $dias === null ? 'Sem sincronização' : ($dias >= 2 ? 'Atenção' : 'Normal');
                                        $estadoClasse = $dias === null ? 'is-muted' : ($dias >= 2 ? 'is-warning' : 'is-active');
                                    @endphp
                                    <td class="table-mono">{{ \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) }}</td>
                                    <td><span class="status-pill {{ $estadoClasse }}">{{ $estadoLabel }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
