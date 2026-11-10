@extends('layouts.app')

@section('title', 'Segurança')

@section('content')
    @include('seguranca.partials.stream-styles')
    <div class="admin-security">
    <div class="page-heading security-heading">
        <div>
            <div class="page-eyebrow"><x-ui-icon name="shield" size="14" /> Central de proteção</div>
            <h1>Segurança</h1>
            <p>Bloqueios, tentativas de acesso e saúde do servidor em um só lugar.</p>
        </div>
        <a href="{{ route('auditoria.index', ['q' => 'auth.']) }}" class="button button-secondary"><x-ui-icon name="list" size="16" />Ver auditoria</a>
    </div>

    <nav class="security-navigation" aria-label="Seções de segurança">
        <a href="#security-active-heading"><x-ui-icon name="shield" size="16" />Bloqueios ativos</a>
        <a href="#security-login-heading"><x-ui-icon name="lock" size="16" />Tentativas de acesso</a>
        <a href="#security-history-heading"><x-ui-icon name="clock" size="16" />Histórico</a>
    </nav>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon amber">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="m10.29 3.86-8.18 14.14A2 2 0 0 0 3.82 21h16.36a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($bansAtivos->count(), 0, ',', '.') }}</div>
            <div class="metric-label">{{ $bansAtivos->count() === 1 ? 'IP bloqueado' : 'IPs bloqueados' }}</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon cyan">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($bansUltimas24h, 0, ',', '.') }}</div>
            <div class="metric-label">{{ $bansUltimas24h === 1 ? 'bloqueio nas últimas 24 h' : 'bloqueios nas últimas 24 h' }}</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon violet">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($loginFalhasUltimas24h, 0, ',', '.') }}</div>
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

    <div class="security-source {{ $estadoConfirmado ? 'is-confirmed' : 'is-pending' }}">
        <x-ui-icon name="{{ $estadoConfirmado ? 'check' : 'info' }}" size="18" />
        <div><strong>{{ $estadoConfirmado ? 'Estado dos bloqueios confirmado pelo Fail2ban' : 'Exibindo o último estado registrado' }}</strong><span>{{ $estadoConfirmado ? 'Informações consultadas ao carregar esta página.' : 'A confirmação do Fail2ban está temporariamente indisponível.' }}</span></div>
    </div>

    <details class="security-note" aria-label="Proteção SSH">
        <summary><span><x-ui-icon name="shield" size="17" />Como funciona a proteção</span><x-ui-icon name="chevron" size="14" /></summary>
        <div class="security-note-content">
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6z"/><path d="m8 12 3 3 5-6"/></svg>
        <div>
            <strong>Proteção SSH e login do painel</strong>
            <p>O SSH e o login do painel estão protegidos por fail2ban: 5 tentativas de senha erradas em 10 minutos geram bloqueio automático por 1 hora. Em caso de reincidência, o tempo aumenta progressivamente até 7 dias, com histórico de 30 dias. As regras são separadas: SSH bloqueia a porta 22; login do painel bloqueia HTTP/HTTPS. Esta tela exibe os eventos registrados.</p>
        </div>
        </div>
    </details>

    @if ($alertasSaude->isNotEmpty())
    <div class="panel security-health-alerts" style="margin-bottom:14px">
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
                <div class="security-section-title"><span class="security-section-icon is-danger"><x-ui-icon name="shield" size="19" /></span><div><h2 id="security-active-heading">Bloqueios ativos — SSH e painel</h2><p>Endereços com bloqueio registrado.</p></div></div>
                <span class="security-status">
                    {{ $bansAtivos->count() > 0 ? $bansAtivos->count().' bloqueios' : 'nenhuma ameaça ativa' }}
                </span>
            </div>
            @if (! $estadoConfirmado)
                <span class="security-recorded-note">Estado pendente de confirmação.</span>
            @endif
            @if ($bansAtivos->isEmpty())
                <div class="security-empty"><span class="security-empty-icon"><x-ui-icon name="shield" size="25" /></span><strong>Nenhum IP bloqueado no momento.</strong><span>Os bloqueios registrados aparecerão aqui.</span></div>
            @else
                <ul class="security-stream" aria-label="IPs bloqueados">
                    @foreach ($bansAtivos as $ban)
                        <li class="security-event security-event-danger">
                            <div class="security-event-top"><strong class="security-action security-mono">{{ $ban->ip_address }}</strong><span class="security-badge is-danger">Bloqueado</span></div>
                            <div class="security-meta">Jail: <span class="security-mono">{{ $ban->jail }}</span></div>
                            @if ($ban->created_at)<div class="security-meta">Bloqueado em <time class="security-mono" datetime="{{ \Illuminate\Support\Carbon::parse($ban->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($ban->created_at)->format('d/m/Y H:i:s') }}</time></div>@else<div class="security-meta">Confirmado pelo Fail2ban; horário de início não registrado.</div>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="panel" aria-labelledby="security-login-heading">
            <div class="panel-header"><div class="security-section-title"><span class="security-section-icon is-warning"><x-ui-icon name="lock" size="19" /></span><div><h2 id="security-login-heading">Últimas falhas de login no painel</h2><p>Últimas 15 tentativas registradas.</p></div></div></div>
            <p class="security-origin-note">O IP de origem identifica o dispositivo ou a conexão que tentou entrar no painel — não é o IP deste servidor.</p>
            @if ($ultimasFalhasLogin->isEmpty())
                <div class="security-empty"><span class="security-empty-icon"><x-ui-icon name="lock" size="25" /></span><strong>Nenhuma falha de login recente.</strong><span>As tentativas recusadas aparecerão aqui.</span></div>
            @else
                <ul class="security-stream" aria-label="Falhas de login">
                    @foreach ($ultimasFalhasLogin as $log)
                        <li class="security-event security-event-warning">
                            <time class="security-mono security-date" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d/m/Y H:i:s') }}</time>
                            <div class="security-event-top"><strong class="security-action">Falha de login</strong><span class="security-badge is-warning">Recusado</span></div>
                            <div class="security-meta"><span class="security-mono">{{ $log->ip_address ?? '-' }}</span> · <span title="{{ $log->description }}">{{ preg_replace('/^Tentativa de login falhou para /u', '', $log->description) }}</span></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="panel security-history" aria-labelledby="security-history-heading">
            <div class="panel-header">
                <div class="security-section-title"><span class="security-section-icon"><x-ui-icon name="clock" size="19" /></span><div><h2 id="security-history-heading">Histórico de bloqueios e desbloqueios</h2><p>Mais recentes primeiro · SSH e login do painel. “Desbloqueado” indica o fim do bloqueio, não um acesso bem-sucedido.</p></div></div>
                <span class="security-status">últimos 50</span>
            </div>
            @if ($historico->isEmpty())
                <div class="security-empty"><span class="security-empty-icon"><x-ui-icon name="clock" size="25" /></span><strong>Nenhum evento de bloqueio registrado.</strong><span>Bloqueios e desbloqueios serão exibidos nesta linha do tempo.</span></div>
            @else
                <ul class="security-stream" aria-label="Histórico SSH">
                    @foreach ($historico as $evento)
                        <li class="security-event {{ $evento->action === 'ban' ? 'security-event-danger' : 'security-event-success' }}">
                            <time class="security-mono security-date" datetime="{{ \Illuminate\Support\Carbon::parse($evento->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($evento->created_at)->format('d/m/Y H:i:s') }}</time>
                            <div>
                                <div class="security-event-top"><strong class="security-action security-mono">{{ $evento->ip_address }}</strong><span class="security-badge {{ $evento->action === 'ban' ? 'is-danger' : 'is-success' }}">{{ $evento->action === 'ban' ? 'Bloqueado' : 'Desbloqueado' }}</span></div>
                                <div class="security-meta">{{ $evento->jail === 'sshd' ? 'Acesso SSH' : ($evento->jail === 'panel-login' ? 'Login do painel' : 'Regra de proteção') }} · <span class="security-mono">{{ $evento->jail }}</span></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
    </div>
@endsection
