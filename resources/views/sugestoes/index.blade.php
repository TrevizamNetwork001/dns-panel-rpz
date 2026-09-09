@extends('layouts.app')

@section('title', 'Sugestões de domínio')

@section('content')
    <div class="page-heading">
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

    <div class="panel" style="margin-bottom:14px">
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

    <div class="panel">
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
                <table class="data-table">
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
                                    <td>{{ $sugestao->empresa->nome }}</td>
                                    <td>{{ $sugestao->criadoPor->name ?? '-' }}</td>
                                    <td class="table-mono">{{ $sugestao->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="table-mono">{{ $sugestao->ip_address ?? '-' }}</td>
                                @endif
                                <td class="table-secondary-text" style="max-width:220px" title="{{ $sugestao->motivo }}">{{ $sugestao->motivo ? \Illuminate\Support\Str::limit($sugestao->motivo, 80) : '-' }}</td>
                                <td>
                                    @if ($sugestao->status === 'pending')
                                        <span class="status-pill is-warning">Pendente</span>
                                    @elseif ($sugestao->status === 'approved')
                                        <span class="status-pill is-active" title="Fonte: {{ $sugestao->lista->nome ?? '-' }}">Aprovada</span>
                                    @else
                                        <span class="status-pill is-inactive">Rejeitada</span>
                                    @endif
                                </td>
                                @if (auth()->user()->isAdmin())
                                    <td>
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
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $sugestoes->links() }}

    @if (auth()->user()->isAdmin() && $sugestoes->contains('status', 'pending'))
        @include('sugestoes.partials.review-dialogs')
    @endif
@endsection
