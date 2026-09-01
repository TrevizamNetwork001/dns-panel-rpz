@extends('layouts.app')

@section('title', 'Segurança')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Sistema</div>
            <h1>Segurança</h1>
            <p>Ameaças detectadas no host (SSH) e falhas de login no painel.</p>
        </div>
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon amber">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="m10.29 3.86-8.18 14.14A2 2 0 0 0 3.82 21h16.36a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ $bansAtivos->count() }}</div>
            <div class="metric-label">{{ $bansAtivos->count() === 1 ? 'IP bloqueado' : 'IPs bloqueados' }}</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon cyan">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ $bansUltimas24h }}</div>
            <div class="metric-label">{{ $bansUltimas24h === 1 ? 'bloqueio na última 24 h' : 'bloqueios nas últimas 24 h' }}</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon violet">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ $loginFalhasUltimas24h }}</div>
            <div class="metric-label">{{ $loginFalhasUltimas24h === 1 ? 'falha de login' : 'falhas de login' }}</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon @if($alertasSaude->isEmpty()) green @else amber @endif">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ ! $ultimoHealthCheck ? 'Sem dados' : ($alertasSaude->isEmpty() ? 'OK' : $alertasSaude->count()) }}</div>
            <div class="metric-label">
                Saúde
                @if ($ultimoHealthCheck)
                    <br><small style="color:var(--text-muted)">última checagem: {{ $ultimoHealthCheck->created_at->diffForHumans() }}</small>
                @else
                    <br><small style="color:var(--text-muted)">ainda não rodou</small>
                @endif
            </div>
        </div>
    </div>

    <div class="panel" style="margin-bottom:14px">
        <div class="alert-error" style="margin:0;background:rgba(33,199,232,0.08);border-color:var(--cyan, #21c7e8);color:var(--text)">
            O SSH do servidor está protegido por <strong>fail2ban</strong>: qualquer IP com 5 tentativas de senha erradas em 10 minutos é bloqueado por 1 hora automaticamente. Esta página mostra o que o fail2ban já bloqueou — não é um firewall configurável por aqui.
        </div>
    </div>

    @if ($alertasSaude->isNotEmpty())
    <div class="panel" style="margin-bottom:14px">
        <div class="panel-header">
            <h2>Alertas de saúde do servidor</h2>
            <span class="status-pill is-warning">requer atenção</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Data</th><th>Alerta</th></tr>
                </thead>
                <tbody>
                    @foreach ($alertasSaude as $alerta)
                        <tr>
                            <td class="table-mono">{{ $alerta->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $alerta->description }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="details-grid">
        <div class="panel">
            <div class="panel-header">
                <h2>IPs bloqueados agora (SSH)</h2>
                <span class="status-pill @if($bansAtivos->count() > 0) is-inactive @else is-active @endif">
                    {{ $bansAtivos->count() > 0 ? 'ameaças ativas' : 'nenhuma ameaça ativa' }}
                </span>
            </div>
            @if ($bansAtivos->isEmpty())
                <div class="empty-state"><span>Nenhum IP bloqueado no momento.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>IP</th><th>Jail</th><th>Bloqueado em</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($bansAtivos as $ban)
                                <tr>
                                    <td class="table-mono">{{ $ban->ip_address }}</td>
                                    <td>{{ $ban->jail }}</td>
                                    <td class="table-mono">{{ \Illuminate\Support\Carbon::parse($ban->created_at)->format('d/m/Y H:i:s') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-header"><h2>Últimas falhas de login no painel</h2></div>
            @if ($ultimasFalhasLogin->isEmpty())
                <div class="empty-state"><span>Nenhuma falha de login recente.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Data</th><th>IP</th><th>Detalhe</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($ultimasFalhasLogin as $log)
                                <tr>
                                    <td class="table-mono">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                                    <td class="table-mono">{{ $log->ip_address ?? '-' }}</td>
                                    <td>{{ $log->description }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Histórico de bloqueios/desbloqueios SSH</h2>
                <span class="status-pill is-muted">últimos 50</span>
            </div>
            @if ($historico->isEmpty())
                <div class="empty-state"><span>Nenhum evento de bloqueio registrado.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Data</th><th>IP</th><th>Jail</th><th>Ação</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($historico as $evento)
                                <tr>
                                    <td class="table-mono">{{ \Illuminate\Support\Carbon::parse($evento->created_at)->format('d/m/Y H:i:s') }}</td>
                                    <td class="table-mono">{{ $evento->ip_address }}</td>
                                    <td>{{ $evento->jail }}</td>
                                    <td>
                                        <span class="status-pill @if($evento->action === 'ban') is-inactive @else is-active @endif">
                                            {{ $evento->action === 'ban' ? 'bloqueado' : 'desbloqueado' }}
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
