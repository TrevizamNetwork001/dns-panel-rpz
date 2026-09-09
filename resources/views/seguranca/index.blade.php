@extends('layouts.app')

@section('title', 'Segurança')

@section('content')
    @include('seguranca.partials.stream-styles')
    <div class="admin-security">
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
            <div class="metric-label">{{ $bansUltimas24h === 1 ? 'bloqueio nas últimas 24 h' : 'bloqueios nas últimas 24 h' }}</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon violet">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ $loginFalhasUltimas24h }}</div>
            <div class="metric-label">{{ $loginFalhasUltimas24h === 1 ? 'falha de login nas últimas 24 h' : 'falhas de login nas últimas 24 h' }}</div>
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

    <aside class="security-note" aria-label="Proteção SSH">
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6z"/><path d="m8 12 3 3 5-6"/></svg>
        <div>
            <strong>Proteção SSH</strong>
            <p>O SSH está protegido por fail2ban: 5 tentativas de senha erradas em 10 minutos geram bloqueio automático por 1 hora. Esta tela apenas exibe o estado; não é um firewall configurável por aqui.</p>
        </div>
    </aside>

    @if ($alertasSaude->isNotEmpty())
    <div class="panel" style="margin-bottom:14px">
        <div class="panel-header">
            <h2>Alertas de saúde do servidor</h2>
            <span class="security-status">requer atenção</span>
        </div>
        <ul class="security-stream" aria-label="Alertas de saúde">
            @foreach ($alertasSaude as $alerta)
                <li class="security-event security-event-warning">
                    <time class="security-mono security-date" datetime="{{ $alerta->created_at->toIso8601String() }}">{{ $alerta->created_at->format('d/m/Y H:i:s') }}</time>
                    <strong class="security-action">{{ $alerta->description }}</strong>
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="security-grid">
        <section class="panel" aria-labelledby="security-active-heading">
            <div class="panel-header">
                <h2 id="security-active-heading">IPs bloqueados agora (SSH)</h2>
                <span class="security-status">
                    {{ $bansAtivos->count() > 0 ? 'ameaças ativas' : 'nenhuma ameaça ativa' }}
                </span>
            </div>
            @if ($bansAtivos->isEmpty())
                <p class="security-empty">Nenhum IP bloqueado no momento.</p>
            @else
                <ul class="security-stream" aria-label="IPs bloqueados">
                    @foreach ($bansAtivos as $ban)
                        <li class="security-event security-event-danger">
                            <strong class="security-action security-mono">{{ $ban->ip_address }}</strong>
                            <div class="security-meta">Jail: <span class="security-mono">{{ $ban->jail }}</span></div>
                            <div class="security-meta">Bloqueado em <time class="security-mono" datetime="{{ \Illuminate\Support\Carbon::parse($ban->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($ban->created_at)->format('d/m/Y H:i:s') }}</time></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="panel" aria-labelledby="security-login-heading">
            <div class="panel-header"><h2 id="security-login-heading">Últimas falhas de login no painel</h2></div>
            <p style="color:var(--text-muted);font-size:11px;margin:0 0 12px">O IP de origem identifica o dispositivo ou a conexão que tentou entrar no painel — não é o IP deste servidor.</p>
            @if ($ultimasFalhasLogin->isEmpty())
                <p class="security-empty">Nenhuma falha de login recente.</p>
            @else
                <ul class="security-stream" aria-label="Falhas de login">
                    @foreach ($ultimasFalhasLogin as $log)
                        <li class="security-event security-event-warning">
                            <time class="security-mono security-date" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d/m/Y H:i:s') }}</time>
                            <strong class="security-action">Falha de login</strong>
                            <div class="security-meta"><span class="security-mono">{{ $log->ip_address ?? '-' }}</span> · <span title="{{ $log->description }}">{{ preg_replace('/^Tentativa de login falhou para /u', '', $log->description) }}</span></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="panel security-history" aria-labelledby="security-history-heading">
            <div class="panel-header">
                <h2 id="security-history-heading">Histórico de bloqueios/desbloqueios SSH</h2>
                <span class="security-status">últimos 50</span>
            </div>
            @if ($historico->isEmpty())
                <p class="security-empty">Nenhum evento de bloqueio registrado.</p>
            @else
                <ul class="security-stream" aria-label="Histórico SSH">
                    @foreach ($historico as $evento)
                        <li class="security-event {{ $evento->action === 'ban' ? 'security-event-danger' : 'security-event-success' }}">
                            <time class="security-mono security-date" datetime="{{ \Illuminate\Support\Carbon::parse($evento->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($evento->created_at)->format('d/m/Y H:i:s') }}</time>
                            <div>
                                <strong class="security-action">{{ $evento->action === 'ban' ? 'Bloqueado' : 'Desbloqueado' }}</strong>
                                <div class="security-meta"><span class="security-mono">{{ $evento->ip_address }}</span> · <span class="security-mono">{{ $evento->jail }}</span></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
    </div>
@endsection
