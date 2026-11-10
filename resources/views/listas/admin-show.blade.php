@extends('layouts.app')
@section('title', $lista->nome)
@section('content')
    @include('listas.partials.admin-detail-styles')
    @php
        $ultimaAtualizacao = $lista->last_sync_at ?? $lista->updated_at;
        $origem = $lista->isAnatel() ? 'ANATEL / PDF' : ($lista->isExterna() ? 'Feed externo' : 'Manual');
    @endphp
    <div class="source-detail">
        <nav class="source-breadcrumb" aria-label="Navegação"><a href="{{ route('listas.index') }}">Fontes</a><x-ui-icon name="chevron" size="13" /><span>{{ $lista->nome }}</span></nav>
        <div class="page-heading">
            <div><div class="page-eyebrow"><x-ui-icon name="list" size="14" />Fonte de proteção</div><h1>{{ $lista->nome }}</h1><p>{{ $lista->descricao ?: 'Configuração e acompanhamento desta fonte.' }}</p></div>
            <div class="page-actions"><a href="{{ route('listas.historico', $lista) }}" class="button button-secondary"><x-ui-icon name="clock" size="15" />Histórico</a><a href="{{ route('listas.edit', $lista) }}" class="button button-primary"><x-ui-icon name="pencil" size="14" />Editar fonte</a></div>
        </div>
        <div class="source-detail-grid">
            <section class="panel" aria-labelledby="source-summary-title">
                <div class="panel-header"><h2 id="source-summary-title">Resumo operacional</h2><span class="status-pill {{ $lista->status === 'active' ? 'is-active' : 'is-inactive' }}">{{ $lista->status === 'active' ? 'Ativa' : 'Inativa' }}</span></div>
                <dl class="details-list">
                    <div><dt>Origem</dt><dd>{{ $origem }}</dd></div>
                    <div><dt>Empresa</dt><dd>{{ $lista->empresa?->nome ?? 'Catálogo — todas as empresas' }}</dd></div>
                    <div><dt>Domínios ativos</dt><dd class="source-number">{{ number_format($lista->dominios_ativos_count, 0, ',', '.') }}</dd></div>
                    <div><dt>Domínios inativos</dt><dd>{{ number_format($lista->dominios_count - $lista->dominios_ativos_count, 0, ',', '.') }}</dd></div>
                    @unless($lista->isExterna())<div><dt>Última atualização</dt><dd><time datetime="{{ $ultimaAtualizacao?->toIso8601String() }}" title="{{ $ultimaAtualizacao?->format('d/m/Y H:i:s') }}">{{ \App\Http\Controllers\DashboardController::relativoPt($ultimaAtualizacao) }}</time></dd></div>@endunless
                </dl>
            </section>
            <section class="panel" aria-labelledby="source-management-title">
                <div class="panel-header"><h2 id="source-management-title">Gerenciar fonte</h2></div>
                <div class="source-shortcuts">
                    <a href="{{ route('dominios.index', ['fonte_id' => $lista->id]) }}"><span class="source-shortcut-icon"><x-ui-icon name="search" size="18" /></span><span><strong>{{ $lista->isExterna() ? 'Ver domínios' : 'Gerenciar domínios' }}</strong><small>Abra a central com esta fonte selecionada.</small></span><x-ui-icon name="chevron" size="13" /></a>
                    <a href="{{ route('listas.edit', $lista) }}#source-servers"><span class="source-shortcut-icon"><x-ui-icon name="server" size="18" /></span><span><strong>Gerenciar vínculos</strong><small>Escolha quais servidores recebem esta fonte.</small></span><x-ui-icon name="chevron" size="13" /></a>
                    <a href="{{ route('listas.rpz-preview', $lista) }}"><span class="source-shortcut-icon"><x-ui-icon name="list" size="18" /></span><span><strong>Preview RPZ</strong><small>Inspecione ou baixe o artefato gerado.</small></span><x-ui-icon name="chevron" size="13" /></a>
                </div>
            </section>
            @if ($lista->isExterna())
                <section class="panel source-wide source-sync" aria-labelledby="source-sync-title">
                    <div class="panel-header"><div><h2 id="source-sync-title">Sincronização automática</h2></div><span class="status-pill {{ $lista->sync_ativo ? 'is-active' : 'is-warning' }}">{{ $lista->sync_ativo ? 'Ativa' : 'Pausada' }}</span></div>
                    <div class="source-sync-fields">
                        <div><span>Última sincronização</span><strong>{{ \App\Http\Controllers\DashboardController::relativoPt($lista->last_sync_at) }}</strong>@if($lista->last_sync_at)<time datetime="{{ $lista->last_sync_at->toIso8601String() }}">{{ $lista->last_sync_at->format('d/m/Y H:i') }}</time>@endif</div>
                        <div><span>URL do feed</span><code>{{ $lista->fonte_url ?? '—' }}</code></div>
                    </div>
                    <div class="source-actions"><form action="{{ route('listas.toggle-sync', $lista) }}" method="POST">@csrf @method('PATCH')<button class="button button-secondary" type="submit">{{ $lista->sync_ativo ? 'Pausar sincronização' : 'Reativar sincronização' }}</button></form><form action="{{ route('listas.sync-now', $lista) }}" method="POST">@csrf<button class="button button-primary" type="submit">Sincronizar agora</button></form></div>
                </section>
            @elseif ($lista->isAnatel())
                <section class="panel source-wide"><div class="panel-header"><div><h2>Importações ANATEL</h2><p>Envie os arquivos, revise a prévia e aprove a publicação.</p></div><a class="button button-primary" href="{{ route('anatel.dashboard', ['lista' => $lista->id]) }}">Importar PDFs</a></div><div class="source-actions"><a class="button button-secondary" href="{{ route('anatel.history', $lista) }}">Histórico de importações</a><a class="button button-secondary" href="{{ route('anatel.exclusions', $lista) }}">Exclusões</a></div></section>
            @endif
        </div>
    </div>
@endsection
