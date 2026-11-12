@extends('layouts.app')

@section('title', 'Auditoria')

@section('content')
    @include('auditoria.partials.stream-assets')
    <div class="admin-audit">
    <div class="page-heading audit-heading">
        <div>
            <div class="page-eyebrow"><x-ui-icon name="activity" size="14" /> Central de rastreabilidade</div>
            <h1>Auditoria</h1>
            <p>Uma visão clara de quem fez o quê no seu painel.</p>
        </div>
        <span class="audit-heading-badge"><x-ui-icon name="clock" size="16" /> Histórico de operações</span>
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon cyan">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($totalFiltered, 0, ',', '.') }}</div>
            <div class="metric-label">Eventos encontrados</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon violet">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($recentCount, 0, ',', '.') }}</div>
            <div class="metric-label">Eventos recentes (24h)</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon amber">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="m10.29 3.86-8.18 14.14A2 2 0 0 0 3.82 21h16.36a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($authFailureCount, 0, ',', '.') }}</div>
            <div class="metric-label">Falhas de autenticação</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon green">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($destructiveCount, 0, ',', '.') }}</div>
            <div class="metric-label">Ações sensíveis</div>
        </div>
    </div>

    <div class="panel audit-filters">
        <div class="panel-header"><div><h2>Encontre um evento</h2><p class="audit-help">Filtre por ação, responsável ou período.</p></div><span class="audit-section-label">Filtros</span></div>
        <form action="{{ route('auditoria.index') }}" method="GET" class="audit-filter-form">
            <div class="field-group">
                <label for="audit-search">Buscar</label>
                <input type="text" id="audit-search" name="q" class="form-control" placeholder="descrição, IP, ação..." value="{{ $query }}">
            </div>
            <div class="field-group">
                <label for="audit-categoria">Categoria</label>
                <select id="audit-categoria" name="categoria" class="form-control">
                    <option value="all" @selected($categoryFilter === 'all')>Todas</option>
                    @foreach (\App\Models\AuditLog::categories() as $value => $label)
                        <option value="{{ $value }}" @selected($categoryFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field-group">
                <label for="audit-usuario">Usuário</label>
                <select id="audit-usuario" name="usuario" class="form-control">
                    <option value="all" @selected($userFilter === 'all')>Todos</option>
                    <option value="sistema" @selected($userFilter === 'sistema')>Sem usuário registrado</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected($userFilter === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field-group">
                <label for="audit-data-inicio">De</label>
                <input type="date" id="audit-data-inicio" name="data_inicio" class="form-control" value="{{ $dataInicio }}">
            </div>
            <div class="field-group">
                <label for="audit-data-fim">Até</label>
                <input type="date" id="audit-data-fim" name="data_fim" class="form-control" value="{{ $dataFim }}">
            </div>
            <div class="field-group">
                <label for="audit-bucket">Severidade</label>
                <select id="audit-bucket" name="bucket" class="form-control">
                    @foreach (['all' => 'Todas', 'danger' => 'Alto', 'warning' => 'Médio', 'info' => 'Info', 'muted' => 'Baixo'] as $value => $label)
                        <option value="{{ $value }}" @selected($bucketFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="audit-filter-footer">
            <label class="audit-routine-toggle">
                <input type="hidden" name="ocultar_rotina" value="0">
                <input type="checkbox" name="ocultar_rotina" value="1" @checked($ocultarRotina)>
                Ocultar rotina (sync, backup, healthcheck)
            </label>
            <div class="audit-filter-actions">
            <button type="submit" class="button button-primary"><x-ui-icon name="search" size="16" /> Aplicar</button>
            @if ($query !== '' || $bucketFilter !== 'all' || $categoryFilter !== 'all' || $userFilter !== 'all' || $dataInicio !== '' || $dataFim !== '' || $ocultarRotina)
                <a href="{{ route('auditoria.index') }}" class="button button-secondary">Limpar</a>
            @endif
            </div>
            </div>
        </form>
        <p class="audit-window-note">Lista e indicadores: até 1.000 resultados mais recentes da busca. A severidade filtra esse conjunto.</p>
    </div>

    <div class="panel audit-results">
        <div class="panel-header">
            <div><h2>Eventos recentes <span class="audit-count">{{ number_format($totalFiltered, 0, ',', '.') }}</span></h2><p class="audit-help">Eventos operacionais, segurança e ações do painel.</p></div>
            <span class="audit-order">Mais recentes primeiro</span>
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
                        <time class="audit-date" datetime="{{ $log->created_at->toIso8601String() }}"><strong>{{ $log->created_at->format('H:i:s') }}</strong><span>{{ $log->created_at->format('d/m/Y') }}</span></time>
                        <div class="audit-content">
                            <div class="audit-action-row"><strong class="audit-action" title="{{ $log->action }}">{{ $actionLabel }}</strong><span class="audit-severity-pill" aria-label="Severidade: {{ $severity }}">{{ $severity }}</span></div>
                            <div class="audit-meta">
                                <span>{{ $log->action === 'rpz.endpoint.denied' ? 'Origem' : 'Quem executou' }}: <strong>{{ $actor }}</strong></span>
                                @if ($target)<span class="audit-context">{{ $target }}</span>@endif
                                @if ($empresaNome)<span class="audit-context">Empresa: {{ $empresaNome }}</span>@endif
                                <span class="audit-context audit-category">Categoria: {{ $categoryLabel }}</span>
                            </div>
                            @if ($log->action === 'rpz.endpoint.denied')
                                <p class="audit-description">{{ str_contains((string) $log->description, '[Teste técnico do painel]') ? 'Teste de acesso realizado pelo próprio painel; a recusa era esperada.' : 'Esta tentativa foi recusada porque o IP de origem não correspondeu aos IPs autorizados da empresa.' }} Este evento registra apenas essa tentativa, não o estado atual dos DNS.</p>
                            @endif
                            @if ($log->description)
                                <p class="audit-description">{{ $log->displayDescription() }}</p>
                            @endif
                            <span class="audit-sr-only">Severidade: {{ $severity }}</span>
                        </div>
                        <span class="audit-ip"><x-ui-icon name="server" size="14" /><span>{{ $log->ip_address ? 'IP: '.$log->ip_address : 'IP não registrado' }}</span></span>
                    </li>
                @endforeach
            </ul>
            <p id="audit-search-empty" class="empty-state" role="status" aria-live="polite" hidden>Nenhum evento corresponde à busca.</p>
            {{ $visibleLogs->links('pagination.compact') }}
        @endif
    </div>
    </div>
@endsection
