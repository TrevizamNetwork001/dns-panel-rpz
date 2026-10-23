@include('servidores.partials.admin-index-styles')

<div class="admin-endpoints-compact">
    <div class="page-heading page-heading-compact">
        <div><h1>Endpoints RPZ</h1><p>{{ $servidores->total() }} cadastrados · {{ $servidoresAtivos }} ativos</p></div>
        <a href="{{ route('servidores.create') }}" class="button button-primary"><x-ui-icon name="plus" />Novo endpoint</a>
    </div>
    <div class="panel admin-endpoints-page">
        @if ($servidores->isEmpty())
            <div class="empty-state empty-state-large"><x-ui-icon name="server" size="32" /><div><strong>Nenhum endpoint cadastrado</strong><span>Cadastre o primeiro endpoint para começar.</span></div></div>
        @else
            <div class="table-responsive"><table class="data-table admin-compact-table">
                <thead><tr><th>Nome</th><th>Tipo</th><th>Listas</th><th>Última consulta</th><th>Status</th><th class="table-actions-column">Ações</th></tr></thead>
                <tbody>
                @foreach ($servidores as $servidor)
                    @php $dias = $servidor->diasSemSincronizar(); $estadoLabel = $dias === null ? 'Sem consulta' : ($dias >= 2 ? 'Atenção' : 'Normal'); $estadoClasse = $dias === null ? 'is-muted' : ($dias >= 2 ? 'is-warning' : 'is-active'); @endphp
                    <tr>
                        <td><a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link admin-server-name"><x-ui-icon name="server" size="18" />{{ $servidor->nome }}</a><small class="admin-server-company">{{ $servidor->empresa->nome }}</small></td>
                        <td><span class="server-icon-label"><x-ui-icon name="server" class="server-muted-icon" />{{ $servidor->tipo_dns === 'mikrotik' ? 'MikroTik (Adlist)' : 'Unbound (RPZ)' }}</span></td>
                        <td>@if ($servidor->listas->isEmpty())<a href="{{ route('servidores.edit', $servidor) }}" class="inline-link admin-list-empty">nenhuma — escolher</a>@else<a href="{{ route('servidores.show', $servidor) }}" class="inline-link" title="{{ $servidor->listas->pluck('nome')->join(', ') }}">{{ $servidor->listas->count() }} {{ $servidor->listas->count() === 1 ? 'lista' : 'listas' }}</a>@endif</td>
                        <td class="table-mono"><span class="server-icon-label"><x-ui-icon name="clock" class="server-muted-icon" />{{ \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) }}</span></td>
                        <td><div class="endpoint-status-stack"><span class="status-pill server-status-pill {{ $estadoClasse }}"><x-ui-icon name="dot" size="14" />{{ $estadoLabel }}</span>@if ($servidor->tipo_dns !== 'mikrotik' && $servidor->unboundVersionAfetada())<span class="status-pill is-warning" title="Unbound {{ $servidor->unboundVersion() }} requer revisão de versão — confira consultas e logs do DNS">Unbound desatualizado</span>@endif<small>{{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }} no painel</small>@if ($servidor->ips_pendentes_count > 0)<span class="status-pill is-warning">IP aguardando liberação</span>@endif</div></td>
                        <td><div class="client-server-actions admin-compact-actions"><a href="{{ route('servidores.show', $servidor) }}" class="button button-secondary admin-compact-details">Ver detalhes</a><a href="{{ route('servidores.edit', $servidor) }}" class="button button-secondary admin-compact-edit" aria-label="Editar {{ $servidor->nome }}" title="Editar"><x-ui-icon name="pencil" size="16" /></a><x-actions-menu label="Ações do endpoint {{ $servidor->nome }}"><a href="{{ route('servidores.show', $servidor) }}" class="actions-menu-item">Ver detalhes</a><a href="{{ route('servidores.edit', $servidor) }}" class="actions-menu-item">Editar</a><div class="actions-menu-divider"></div><form action="{{ route('servidores.destroy', $servidor) }}" method="POST">@csrf @method('DELETE')<button type="submit" class="actions-menu-item actions-menu-item-danger" onclick="return confirm('Remover endpoint?')">Remover</button></form></x-actions-menu></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    {{ $servidores->links('pagination.compact') }}
</div>
