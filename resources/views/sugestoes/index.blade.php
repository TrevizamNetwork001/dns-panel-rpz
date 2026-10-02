@extends('layouts.app')

@section('title', 'Sugestões de domínio')

@section('content')
    <style>
        .admin-suggestions-compact { max-width: 1400px; margin: 0 auto; }
        .admin-suggestions-compact .page-heading { align-items: center; margin-bottom: 22px; }
        .admin-suggestions-compact .page-heading h1 { font-size: 28px; letter-spacing: -.03em; }
        .admin-suggestions-compact .page-heading p { color: var(--text-soft); font-size: 12px; }
        .admin-suggestions-compact .page-heading .button { min-height: 40px; }
        .admin-suggestions-compact .suggestions-filter { margin-bottom: 14px; padding: 14px 16px; }
        .admin-suggestions-compact .suggestions-filter form { gap: 10px; }
        .admin-suggestions-compact .suggestions-filter .field-group { min-width: 220px; }
        .admin-suggestions-compact .suggestions-page { padding: 0; overflow: hidden; }
        .admin-suggestions-compact .suggestions-table { table-layout: fixed; }
        .admin-suggestions-compact .suggestions-table th { padding: 13px 12px; }
        .admin-suggestions-compact .suggestions-table td { padding: 16px 12px; vertical-align: middle; }
        .admin-suggestions-compact .suggestions-table td:first-child { padding-left: 18px; }
        .admin-suggestions-compact .suggestions-table tbody tr:hover { background: var(--surface-soft); }
        .admin-suggestions-compact .suggestions-actions { display: flex; justify-content: flex-end; }
        .admin-suggestions-compact .actions-menu-toggle { width: 31px; height: 31px; }
        @media (max-width: 700px) {
            .admin-suggestions-compact .page-heading { align-items: flex-start; flex-direction: column; }
            .admin-suggestions-compact .suggestions-filter form { align-items: stretch !important; }
            .admin-suggestions-compact .suggestions-filter .field-group { min-width: 100%; }
            .admin-suggestions-compact .suggestions-table thead { display: none; }
            .admin-suggestions-compact .suggestions-table, .admin-suggestions-compact .suggestions-table tbody, .admin-suggestions-compact .suggestions-table tr { display: block; width: 100%; }
            .admin-suggestions-compact .suggestions-table tr { padding: 14px 16px; border-top: 1px solid var(--border-soft); }
            .admin-suggestions-compact .suggestions-table td, .admin-suggestions-compact .suggestions-table td:first-child { display: block; width: 100%; max-width: none !important; padding: 7px 0; border: 0; }
            .admin-suggestions-compact .suggestions-table td:not(:first-child)::before { content: attr(data-label); display: block; margin-bottom: 4px; color: var(--text-muted); font-size: 9px; text-transform: uppercase; }
            .admin-suggestions-compact .suggestions-actions { justify-content: flex-start; margin-top: 6px; }
        }
    </style>
    <div class="admin-suggestions-compact">
    <div class="page-heading page-heading-compact">
        <div>
            <div class="page-eyebrow">Sugestões</div>
            <h1>Sugestões de domínio</h1>
            <p>{{ auth()->user()->isAdmin() ? 'Todas as sugestões recebidas de todas as empresas.' : 'Sugestões enviadas pela sua empresa.' }}</p>
        </div>
        <div class="page-actions">
            @unless (auth()->user()->isAdmin())
            <a href="{{ route('sugestoes.create') }}" class="button button-primary">+ Nova sugestão</a>
            @endunless
        </div>
    </div>

    <div class="panel suggestions-filter">
        <form action="{{ route('sugestoes.index') }}" method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ route('sugestoes.index', array_filter(['q' => $busca])) }}" class="button {{ $statusFiltro === null ? 'button-primary' : 'button-secondary' }}">Todas</a>
                @foreach (['pending' => 'Pendentes', 'approved' => 'Aprovadas', 'rejected' => 'Rejeitadas'] as $value => $label)
                    <a href="{{ route('sugestoes.index', array_filter(['status' => $value, 'q' => $busca])) }}" class="button {{ $statusFiltro === $value ? 'button-primary' : 'button-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="field-group" style="margin:0;min-width:210px;flex:1">
                <label for="sugestoes-q">Buscar domínio</label>
                <input id="sugestoes-q" type="search" name="q" class="form-control" value="{{ $busca }}" placeholder="exemplo.com">
            </div>
            @if ($statusFiltro !== null)<input type="hidden" name="status" value="{{ $statusFiltro }}">@endif
            <button type="submit" class="button button-secondary">Buscar</button>
            @if ($busca !== '')
                <a href="{{ route('sugestoes.index', array_filter(['status' => $statusFiltro])) }}" class="button button-secondary">Limpar</a>
            @endif
        </form>
    </div>

    <div class="panel suggestions-page">
        @if ($sugestoes->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">◇</div>
                <div>
                    @if ($statusFiltro === 'pending' && $busca === '')
                        <strong>Nenhuma sugestão aguardando análise.</strong>
                    @elseif ($statusFiltro !== null || $busca !== '')
                        <strong>Nenhuma sugestão corresponde aos filtros selecionados.</strong>
                    @else
                        <strong>Nenhuma sugestão ainda</strong>
                        <span>{{ auth()->user()->isAdmin() ? 'As sugestões enviadas pelos clientes aparecerão aqui.' : 'Sugira um domínio que deveria ser bloqueado.' }}</span>
                    @endif
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table suggestions-table">
                    <thead>
                        <tr>
                            <th>Domínio</th>
                            @if (auth()->user()->isAdmin())
                                <th>Empresa</th>
                                <th>Enviado por</th>
                                <th>Data</th>
                                <th>IP</th>
                            @endif
                            <th>Motivo</th>
                            <th>Status</th>
                            @if (auth()->user()->isAdmin())
                                <th class="table-actions-column"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sugestoes as $sugestao)
                            <tr>
                                <td class="table-mono"><strong>{{ $sugestao->dominio }}</strong></td>
                                @if (auth()->user()->isAdmin())
                                    <td data-label="Empresa">{{ $sugestao->empresa->nome }}</td>
                                    <td data-label="Enviado por">{{ $sugestao->criadoPor->name ?? '-' }}</td>
                                    <td class="table-mono" data-label="Data">{{ $sugestao->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="table-mono" data-label="IP">{{ $sugestao->ip_address ?? '-' }}</td>
                                @endif
                                <td class="table-secondary-text" data-label="Motivo" style="max-width:220px" title="{{ $sugestao->motivo }}">{{ $sugestao->motivo ? \Illuminate\Support\Str::limit($sugestao->motivo, 80) : '-' }}</td>
                                <td data-label="Status">
                                    @if ($sugestao->status === 'pending')
                                        <span class="status-pill is-warning">Pendente</span>
                                    @elseif ($sugestao->status === 'approved')
                                        <span class="status-pill is-active" title="Fonte: {{ $sugestao->lista->nome ?? '-' }}">Aprovada</span>
                                    @else
                                        <span class="status-pill is-inactive">Rejeitada</span>
                                    @endif
                                </td>
                                @if (auth()->user()->isAdmin())
                                    <td data-label="Ações"><div class="suggestions-actions">
                                        @if ($sugestao->status === 'pending')
                                            <x-actions-menu label="Ações da sugestão {{ $sugestao->dominio }}">
                                                @foreach (['aprovar' => 'Aprovar', 'rejeitar' => 'Rejeitar'] as $action => $label)
                                                    <button type="button" class="actions-menu-item {{ $action === 'rejeitar' ? 'actions-menu-item-danger' : '' }}" data-suggestion-action="{{ $action }}" data-url="{{ route('sugestoes.'.$action, $sugestao) }}" aria-haspopup="dialog" aria-controls="suggestion-{{ $action }}">{{ $label }}</button>
                                                @endforeach
                                                <template>
                                                    <dl class="suggestion-context">
                                                        <div><dt>Domínio</dt><dd data-domain>{{ $sugestao->dominio }}</dd></div>
                                                        <div><dt>Empresa</dt><dd>{{ $sugestao->empresa->nome }}</dd></div>
                                                        <div><dt>Enviado por</dt><dd>{{ $sugestao->criadoPor->name ?? '-' }}</dd></div>
                                                        <div><dt>Motivo</dt><dd>{{ $sugestao->motivo ?: '-' }}</dd></div>
                                                    </dl>
                                                </template>
                                            </x-actions-menu>
                                        @endif
                                    </div></td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $sugestoes->links('pagination.compact') }}

    </div>

    @if (auth()->user()->isAdmin() && $sugestoes->contains('status', 'pending'))
        @include('sugestoes.partials.review-dialogs')
    @endif
@endsection
