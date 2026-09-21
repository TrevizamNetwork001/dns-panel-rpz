@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @include('dashboard.partials.cliente-dashboard-styles')
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

    @php
        $intervaloMedioTexto = $intervaloMedioMinutos === null
            ? '—'
            : ($intervaloMedioMinutos >= 60
                ? intdiv($intervaloMedioMinutos, 60).'h '.($intervaloMedioMinutos % 60).'min'
                : $intervaloMedioMinutos.'min');
        $coberturaTexto = $coberturaLicenca === null ? 'Sem licença ativa' : $coberturaLicenca.'%';
    @endphp
    <section class="panel cliente-rpz-health dashboard-grid-cliente" aria-labelledby="cliente-rpz-health-title">
        <div class="cliente-health-heading">
            <div class="cliente-health-title"><x-ui-icon name="activity" size="31" /><div><h2 id="cliente-rpz-health-title">Saúde da distribuição RPZ</h2><p>Acompanhe as sincronizações, o status dos servidores e a entrega das listas.</p></div></div>
            <span class="status-pill is-muted">Últimos 30 dias</span>
        </div>
        <div class="cliente-health-metrics">
            <div class="cliente-health-metric"><span class="cliente-health-metric-icon is-cyan"><x-ui-icon name="search" size="22" /></span><div><strong>{{ number_format($logsPeriodo->count(), 0, ',', '.') }}</strong><span>buscas no período</span></div><small>últimos 30 dias</small></div>
            <div class="cliente-health-metric"><span class="cliente-health-metric-icon is-green"><x-ui-icon name="clock" size="22" /></span><div><strong>{{ $ultimaSincronizacao ? \App\Http\Controllers\DashboardController::relativoPt($ultimaSincronizacao) : '—' }}</strong><span>última sincronização</span></div><small>{{ $ultimaSincronizacao?->format('d/m/Y H:i') ?? 'Ainda não executada' }}</small></div>
            <div class="cliente-health-metric"><span class="cliente-health-metric-icon is-cyan"><x-ui-icon name="refresh" size="22" /></span><div><strong>{{ $intervaloMedioTexto }}</strong><span>intervalo médio entre buscas</span></div><small>{{ $intervaloMedioMinutos === null ? 'Dados insuficientes' : 'com base nas buscas registradas' }}</small></div>
            <div class="cliente-health-metric"><span class="cliente-health-metric-icon is-green"><x-ui-icon name="shield" size="22" /></span><div><strong>{{ $coberturaTexto }}</strong><span>cobertura da licença</span></div><small>{{ $capacidadeLicenca > 0 ? $totalServidores.' de '.$capacidadeLicenca.' servidores' : 'Sem capacidade disponível' }}</small></div>
        </div>
        <div class="cliente-health-panels">
            <div class="cliente-health-chart">@include('dashboard.partials.grafico-atualizacoes', ['pontos' => $graficoAtualizacoes])</div>
            <div class="cliente-health-status">
                <div class="cliente-health-subheading"><div><x-ui-icon name="server" size="22" /><h3>Status dos servidores</h3></div><span>{{ $totalServidores }} {{ $totalServidores === 1 ? 'servidor' : 'servidores' }}</span></div>
                @if ($totalServidores === 0)
                    <div class="empty-state"><span>Nenhum servidor cadastrado ainda.</span></div>
                @else
                    @php $percentualEmDia = $totalServidores > 0 ? (int) round(($statusServidores['normal'] / $totalServidores) * 100) : 0; $circunferencia = 251.2; $arcoEmDia = round($circunferencia * $percentualEmDia / 100, 1); @endphp
                    <div class="cliente-status-summary">
                        <svg class="cliente-status-donut" viewBox="0 0 100 100" role="img" aria-label="{{ $statusServidores['normal'] }} de {{ $totalServidores }} servidores em dia"><circle class="cliente-status-donut-track" cx="50" cy="50" r="40" /><circle class="cliente-status-donut-value" cx="50" cy="50" r="40" stroke-dasharray="{{ $arcoEmDia }} {{ $circunferencia }}" /><text x="50" y="48" text-anchor="middle">{{ $statusServidores['normal'] }}</text><text class="cliente-status-donut-label" x="50" y="61" text-anchor="middle">de {{ $totalServidores }}</text></svg>
                        <div><strong>{{ $statusServidores['normal'] }}</strong><span>em dia</span><small>{{ $percentualEmDia }}% dos servidores</small></div>
                    </div>
                    <div class="cliente-status-list">
                        @foreach ($servidores as $servidor)
                            @php $dias = $servidor->diasSemSincronizar(); $statusLabel = $dias === null ? 'Sem sincronização' : ($dias >= 2 ? 'Atenção' : 'Em dia'); $statusClasse = $dias === null ? 'is-muted' : ($dias >= 2 ? 'is-warning' : 'is-active'); @endphp
                            <a href="{{ route('servidores.show', $servidor) }}" class="cliente-status-item"><span class="cliente-status-dot {{ $statusClasse }}"></span><span><strong>{{ $servidor->nome }}</strong><small>Última sincronização: {{ \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) }}</small></span><span class="status-pill {{ $statusClasse }}">{{ $statusLabel }}</span></a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
