@include('servidores.partials.admin-index-styles')
<style>
    .company-servers { margin-bottom: 24px; }
    .company-servers > .panel-header { align-items: center; flex-wrap: wrap; gap: 12px; }
    .company-servers .admin-compact-actions { flex-wrap: wrap; }
    .company-servers > .panel-header p { margin: 5px 0 0; color: var(--text-soft); font-size: 12px; }
</style>
<section class="admin-endpoints-compact company-servers" aria-labelledby="company-servers-title">
    <div class="panel-header">
        <div><h2 id="company-servers-title">Servidores</h2><p>{{ $empresa->servidores->count() }} cadastrados nesta empresa</p></div>
        <a href="{{ route('servidores.create', ['empresa_id' => $empresa->id]) }}" class="button button-primary"><x-ui-icon name="plus" />Novo servidor</a>
    </div>
    <div class="panel admin-endpoints-page">
        @if ($empresa->servidores->isEmpty())
            <div class="empty-state empty-state-large"><x-ui-icon name="server" :size="32" /><div><strong>Nenhum servidor cadastrado</strong><span>Cadastre o primeiro servidor desta empresa.</span></div></div>
        @else
            <div class="table-responsive"><table class="data-table admin-compact-table">
                <thead><tr><th>Nome</th><th>Tipo</th><th>Listas</th><th>Última consulta</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                    @foreach ($empresa->servidores->sortBy('nome') as $servidor)
                        @php $dias = $servidor->diasSemSincronizar(); @endphp
                        <tr>
                            <td><a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link admin-server-name"><x-ui-icon name="server" />{{ $servidor->nome }}</a></td>
                            <td data-label="Tipo">{{ $servidor->tipo_dns === 'mikrotik' ? 'MikroTik (Adlist)' : 'Unbound (RPZ)' }}</td>
                            <td data-label="Listas">{{ $servidor->listas->count() }} {{ $servidor->listas->count() === 1 ? 'lista' : 'listas' }}</td>
                            <td data-label="Última consulta">{{ \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) }}</td>
                            <td data-label="Status"><div class="endpoint-status-stack">
                                <span class="status-pill {{ $dias === null ? 'is-muted' : ($dias >= 2 ? 'is-warning' : 'is-active') }}">{{ $dias === null ? 'Sem consulta' : ($dias >= 2 ? 'Atenção' : 'Em dia') }}</span>
                                <small>{{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }} no painel</small>
                                @if ($servidor->allowedIps->contains('status', 'pending'))<span class="status-pill is-warning">IP aguardando liberação</span>@endif
                            </div></td>
                            <td><div class="admin-compact-actions"><a href="{{ route('servidores.show', $servidor) }}" class="button button-secondary">Ver detalhes</a><a href="{{ route('servidores.edit', $servidor) }}" class="button button-secondary admin-compact-edit" aria-label="Editar {{ $servidor->nome }}"><x-ui-icon name="pencil" /></a><a href="{{ route('servidores.show', $servidor) }}#endpoint-ips" class="button button-secondary">Gerenciar ACL</a></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
</section>
