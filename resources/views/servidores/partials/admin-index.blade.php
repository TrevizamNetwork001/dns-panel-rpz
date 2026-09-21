@include('servidores.partials.admin-index-styles')
<div class="endpoint-directory">
    <div class="page-heading">
        <div><div class="page-eyebrow">Operação · DNS</div><h1>Endpoints RPZ</h1><p>Servidores, listas vinculadas e últimas consultas.</p></div>
        <a href="{{ route('servidores.create') }}" class="button button-primary"><x-ui-icon name="plus" size="18" />Novo endpoint</a>
    </div>
    <div class="endpoint-directory-summary" aria-label="Resumo dos endpoints">
        <div><span class="endpoint-summary-icon"><x-ui-icon name="server" size="23" /></span><div><span>Endpoints cadastrados</span><strong>{{ $servidores->total() }}</strong></div></div>
        <div><span class="endpoint-summary-icon is-green"><x-ui-icon name="check" size="23" /></span><div><span>Ativos no painel</span><strong>{{ $servidoresAtivos }}</strong></div></div>
        <div><span class="endpoint-summary-icon is-muted"><x-ui-icon name="server" size="23" /></span><div><span>Inativos no painel</span><strong>{{ $servidores->total() - $servidoresAtivos }}</strong></div></div>
    </div>
    <section class="panel endpoint-directory-panel">
        <div class="panel-header"><div class="endpoint-directory-title"><x-ui-icon name="network" size="23" /><h2>Servidores cadastrados</h2></div><span>Consultas mais recentes primeiro</span></div>
        @if ($servidores->isEmpty())
            <div class="empty-state empty-state-large"><x-ui-icon name="server" size="32" /><div><strong>Nenhum endpoint cadastrado</strong><span>Cadastre o primeiro endpoint para começar.</span></div></div>
        @else
            <div class="endpoint-directory-table-wrap">
                <table class="data-table endpoint-directory-table">
                    <thead><tr><th>Servidor / DNS</th><th>Empresa</th><th>Listas vinculadas</th><th>Última consulta</th><th>Status</th><th class="table-actions-column">Ações</th></tr></thead>
                    <tbody>
                        @foreach ($servidores as $servidor)
                            @php
                                $dias = $servidor->diasSemSincronizar();
                                $estadoLabel = $dias === null ? 'Sem consulta' : ($dias >= 2 ? 'Atenção' : 'Normal');
                                $estadoClasse = $dias === null ? 'is-muted' : ($dias >= 2 ? 'is-warning' : 'is-active');
                            @endphp
                            <tr>
                                <td class="endpoint-directory-server"><div class="endpoint-server-identity"><span class="endpoint-server-glyph"><x-ui-icon name="server" size="24" /></span><div><a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link">{{ $servidor->nome }}</a><small>{{ $servidor->tipo_dns === 'mikrotik' ? 'MikroTik · Adlist' : 'Unbound · RPZ' }}</small></div></div></td>
                                <td data-label="Empresa"><a href="{{ route('empresas.show', $servidor->empresa) }}" class="endpoint-directory-company"><x-ui-icon name="building" size="17" /><span>{{ $servidor->empresa->nome }}</span></a></td>
                                <td data-label="Listas vinculadas">
                                    @if ($servidor->listas->isEmpty())
                                        <a href="{{ route('servidores.edit', $servidor) }}" class="endpoint-list-count is-empty"><x-ui-icon name="plus" size="15" />Vincular listas</a>
                                    @else
                                        <a href="{{ route('servidores.show', $servidor) }}" class="endpoint-list-count" title="{{ $servidor->listas->pluck('nome')->join(', ') }}"><x-ui-icon name="link" size="16" />{{ $servidor->listas->count() }} {{ $servidor->listas->count() === 1 ? 'lista' : 'listas' }}</a>
                                    @endif
                                </td>
                                <td data-label="Última consulta"><div class="endpoint-directory-consultation"><x-ui-icon name="clock" size="17" /><div><span>{{ \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) }}</span>@if ($servidor->last_synced_at)<small>{{ $servidor->last_synced_at->format('d/m/Y H:i') }}</small>@endif</div></div></td>
                                <td data-label="Status"><div class="endpoint-directory-status"><span class="status-pill {{ $estadoClasse }}"><x-ui-icon name="dot" size="13" />{{ $estadoLabel }}</span><small>{{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }} no painel</small>@if ($servidor->ips_pendentes_count > 0)<a href="{{ route('servidores.show', $servidor) }}" class="endpoint-pending-ip"><x-ui-icon name="shield" size="13" />IP aguardando liberação</a>@endif</div></td>
                                <td class="endpoint-directory-actions-cell"><div class="endpoint-directory-actions">
                                    <a href="{{ route('servidores.show', $servidor) }}" class="button button-secondary endpoint-details-action" aria-label="Ver detalhes de {{ $servidor->nome }}">Ver detalhes<x-ui-icon name="chevron" size="14" /></a>
                                    <a href="{{ route('servidores.edit', $servidor) }}" class="button button-secondary endpoint-edit-action" aria-label="Editar {{ $servidor->nome }}" title="Editar"><x-ui-icon name="pencil" size="17" /></a>
                                    <x-actions-menu label="Ações do endpoint {{ $servidor->nome }}"><a href="{{ route('servidores.show', $servidor) }}" class="actions-menu-item"><x-ui-icon name="server" />Ver detalhes</a><a href="{{ route('servidores.edit', $servidor) }}" class="actions-menu-item"><x-ui-icon name="pencil" />Editar</a><div class="actions-menu-divider"></div><form action="{{ route('servidores.destroy', $servidor) }}" method="POST">@csrf @method('DELETE')<button type="submit" class="actions-menu-item actions-menu-item-danger" onclick="return confirm('Remover endpoint?')"><x-ui-icon name="trash" />Remover</button></form></x-actions-menu>
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="endpoint-directory-footer"><span>{{ $servidores->firstItem() }}–{{ $servidores->lastItem() }} de {{ $servidores->total() }} endpoints</span>{{ $servidores->links('pagination.compact') }}</div>
        @endif
    </section>
</div>
