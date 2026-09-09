@extends('layouts.app')

@section('title', 'Auditoria')

@section('content')
    @include('auditoria.partials.stream-assets')
    <div class="admin-audit">
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Rastreabilidade</div>
            <h1>Auditoria</h1>
            <p>Eventos operacionais, segurança e ações do painel.</p>
        </div>
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon cyan">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ $visibleCount }}</div>
            <div class="metric-label">Eventos exibidos</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon violet">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ $recentCount }}</div>
            <div class="metric-label">Eventos recentes (24h)</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon amber">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="m10.29 3.86-8.18 14.14A2 2 0 0 0 3.82 21h16.36a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ $authFailureCount }}</div>
            <div class="metric-label">Falhas de autenticação</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon green">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ $destructiveCount }}</div>
            <div class="metric-label">Ações sensíveis</div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Filtros</h2></div>
        <p style="color:var(--text-muted);font-size:11px;margin:0 0 14px">Pesquisa por ação, alvo, ator ou IP. Os filtros ficam restritos aos 200 últimos eventos.</p>
        <form action="{{ route('auditoria.index') }}" method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="field-group" style="margin:0;min-width:0;width:260px;max-width:100%">
                <label for="audit-search">Buscar</label>
                <input type="text" id="audit-search" name="q" class="form-control" placeholder="auth, servidor, empresa, IP" value="{{ $query }}">
            </div>
            <div class="field-group" style="margin:0;min-width:160px">
                <label for="audit-bucket">Severidade</label>
                <select id="audit-bucket" name="bucket" class="form-control">
                    @foreach (['all' => 'Todas', 'danger' => 'Alto', 'warning' => 'Médio', 'info' => 'Info', 'muted' => 'Baixo'] as $value => $label)
                        <option value="{{ $value }}" @selected($bucketFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="button button-primary">Aplicar</button>
            @if ($query !== '' || $bucketFilter !== 'all')
                <a href="{{ route('auditoria.index') }}" class="button button-secondary">Limpar</a>
            @endif
        </form>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2>Eventos recentes</h2>
        </div>

        @if ($visibleLogs->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">◇</div>
                <div>
                    <strong>Nenhum evento encontrado</strong>
                    <span>Ajuste os filtros ou aguarde novas ações do painel.</span>
                </div>
            </div>
        @else
            <ul class="audit-stream" aria-label="Eventos de auditoria">
                @foreach ($visibleLogs as $log)
                    @php
                        $bucket = \App\Models\AuditLog::bucket($log->action);
                        $severity = \App\Models\AuditLog::bucketLabel($bucket);
                        $actionLabel = match ($log->action) {
                            'auth.logout' => 'Sessão encerrada',
                            'user.password_reset' => 'Senha de usuário redefinida',
                            'rpz.endpoint.downloaded' => 'Zona RPZ baixada',
                            default => \App\Models\AuditLog::actionLabel($log->action),
                        };
                        $actor = $log->user->name ?? 'sistema';
                        $target = $log->target_type ? $log->target_type.' #'.$log->target_id : ($log->empresa->nome ?? '-');
                        $date = $log->created_at->format('d/m/Y H:i:s');
                        $searchText = mb_strtolower(implode(' ', [$log->action, $actionLabel, $actor, $target, $log->ip_address, $date, $severity, $log->description, $log->empresa->nome ?? '']));
                        $routine = in_array($log->action, ['health.ok', 'rpz.endpoint.downloaded'], true);
                    @endphp
                    <li class="audit-event audit-severity-{{ $bucket }}{{ $routine ? ' audit-event-routine' : '' }}" data-audit-search="{{ $searchText }}">
                        <time class="audit-date" datetime="{{ $log->created_at->toIso8601String() }}">{{ $date }}</time>
                        <div class="audit-content">
                            <strong class="audit-action" title="{{ $log->action }}">{{ $actionLabel }}</strong>
                            <div class="audit-meta">{{ $actor }} <span aria-hidden="true">·</span> {{ $target }}</div>
                            <span class="audit-sr-only">Severidade: {{ $severity }}</span>
                        </div>
                        <span class="audit-ip"><span class="audit-sr-only">IP: </span>{{ $log->ip_address ?? '-' }}</span>
                    </li>
                @endforeach
            </ul>
            <p id="audit-search-empty" class="empty-state" role="status" aria-live="polite" hidden>Nenhum evento corresponde à busca.</p>
        @endif
    </div>
    </div>
@endsection
