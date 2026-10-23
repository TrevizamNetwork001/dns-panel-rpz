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
            <div class="metric-value">{{ $totalFiltered }}</div>
            <div class="metric-label">Eventos encontrados</div>
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
        <p style="color:var(--text-muted);font-size:11px;margin:0 0 14px">Busque por descrição, IP, ação, usuário ou empresa. A lista e os totais consideram até 1.000 resultados mais recentes da busca; a severidade filtra esses resultados.</p>
        <form action="{{ route('auditoria.index') }}" method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="field-group" style="margin:0;min-width:0;width:220px;max-width:100%">
                <label for="audit-search">Buscar</label>
                <input type="text" id="audit-search" name="q" class="form-control" placeholder="descrição, IP, ação..." value="{{ $query }}">
            </div>
            <div class="field-group" style="margin:0;min-width:180px">
                <label for="audit-categoria">Categoria</label>
                <select id="audit-categoria" name="categoria" class="form-control">
                    <option value="all" @selected($categoryFilter === 'all')>Todas</option>
                    @foreach (\App\Models\AuditLog::categories() as $value => $label)
                        <option value="{{ $value }}" @selected($categoryFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field-group" style="margin:0;min-width:160px">
                <label for="audit-usuario">Usuário</label>
                <select id="audit-usuario" name="usuario" class="form-control">
                    <option value="all" @selected($userFilter === 'all')>Todos</option>
                    <option value="sistema" @selected($userFilter === 'sistema')>Sem usuário registrado</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected($userFilter === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field-group" style="margin:0;min-width:140px">
                <label for="audit-data-inicio">De</label>
                <input type="date" id="audit-data-inicio" name="data_inicio" class="form-control" value="{{ $dataInicio }}">
            </div>
            <div class="field-group" style="margin:0;min-width:140px">
                <label for="audit-data-fim">Até</label>
                <input type="date" id="audit-data-fim" name="data_fim" class="form-control" value="{{ $dataFim }}">
            </div>
            <div class="field-group" style="margin:0;min-width:140px">
                <label for="audit-bucket">Severidade</label>
                <select id="audit-bucket" name="bucket" class="form-control">
                    @foreach (['all' => 'Todas', 'danger' => 'Alto', 'warning' => 'Médio', 'info' => 'Info', 'muted' => 'Baixo'] as $value => $label)
                        <option value="{{ $value }}" @selected($bucketFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <label class="field-group" style="margin:0;flex-direction:row;align-items:center;gap:6px;white-space:nowrap">
                <input type="hidden" name="ocultar_rotina" value="0">
                <input type="checkbox" name="ocultar_rotina" value="1" @checked($ocultarRotina)>
                Ocultar rotina (sync, backup, healthcheck)
            </label>
            <button type="submit" class="button button-primary">Aplicar</button>
            @if ($query !== '' || $bucketFilter !== 'all' || $categoryFilter !== 'all' || $userFilter !== 'all' || $dataInicio !== '' || $dataFim !== '' || $ocultarRotina)
                <a href="{{ route('auditoria.index') }}" class="button button-secondary">Limpar</a>
            @endif
        </form>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2>Eventos recentes</h2><span style="color:var(--text-muted);font-size:11px">Mais recentes primeiro</span>
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
                        $actionLabel = \App\Models\AuditLog::actionLabel($log->action);
                        $categoryLabel = \App\Models\AuditLog::categoryLabel($log->action);
                        $actor = $log->actorLabel();
                        $target = \App\Models\AuditLog::targetLabel($log->target_type, $log->target_id);
                        $targetName = ($targetNames[$log->target_type] ?? collect())->get($log->target_id);
                        if ($targetName) $target .= ' — '.$targetName;
                        $empresaNome = $log->empresa->nome ?? null;
                        if ($log->target_type === 'empresa' && $targetName === $empresaNome) $empresaNome = null;
                        $date = $log->created_at->format('d/m/Y H:i:s');
                        $searchText = mb_strtolower(implode(' ', [$log->action, $actionLabel, $actor, $target, $log->ip_address, $date, $severity, $log->description, $empresaNome ?? '']));
                        $routine = \App\Models\AuditLog::isRotina($log->action);
                    @endphp
                    <li class="audit-event audit-severity-{{ $bucket }}{{ $routine ? ' audit-event-routine' : '' }}" data-audit-search="{{ $searchText }}">
                        <time class="audit-date" datetime="{{ $log->created_at->toIso8601String() }}">{{ $date }}</time>
                        <div class="audit-content">
                            <strong class="audit-action" title="{{ $log->action }}">{{ $actionLabel }}</strong>
                            <div class="audit-meta">
                                <span>{{ $log->action === 'rpz.endpoint.denied' ? 'Origem' : 'Quem executou' }}: <strong>{{ $actor }}</strong></span>
                                @if ($target)<span class="audit-context">{{ $target }}</span>@endif
                                @if ($empresaNome)<span class="audit-context">Empresa: {{ $empresaNome }}</span>@endif
                                <span class="audit-context">Categoria: {{ $categoryLabel }}</span>
                            </div>
                            @if ($log->action === 'rpz.endpoint.denied')
                                <p class="audit-description">{{ str_contains((string) $log->description, '[Teste técnico do painel]') ? 'Teste de acesso realizado pelo próprio painel; a recusa era esperada.' : 'Esta tentativa foi recusada porque o IP de origem não correspondeu aos IPs autorizados da empresa.' }} Este evento registra apenas essa tentativa, não o estado atual dos DNS.</p>
                            @endif
                            @if ($log->description)
                                <details class="audit-details"><summary>Ver detalhes</summary><div class="audit-description">{{ $log->displayDescription() }}</div></details>
                            @endif
                            <span class="audit-sr-only">Severidade: {{ $severity }}</span>
                        </div>
                        <span class="audit-ip">{{ $log->ip_address ? 'IP: '.$log->ip_address : 'IP não registrado' }}</span>
                    </li>
                @endforeach
            </ul>
            <p id="audit-search-empty" class="empty-state" role="status" aria-live="polite" hidden>Nenhum evento corresponde à busca.</p>
            {{ $visibleLogs->links('pagination.compact') }}
        @endif
    </div>
    </div>
@endsection
