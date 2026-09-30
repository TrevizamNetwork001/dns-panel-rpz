@extends('layouts.app')

@section('title', 'Empresas')

@section('content')
    <style>
        .admin-companies-compact { max-width: 1400px; margin: 0 auto; }
        .admin-companies-compact .page-heading { align-items: center; margin-bottom: 22px; }
        .admin-companies-compact .page-heading h1 { font-size: 28px; letter-spacing: -.03em; }
        .admin-companies-compact .page-heading p { color: var(--text-soft); font-size: 12px; }
        .admin-companies-compact .page-heading .button { min-height: 40px; }
        .admin-companies-compact .companies-page { padding: 0; overflow: hidden; }
        .admin-companies-compact .companies-table { table-layout: fixed; }
        .admin-companies-compact .companies-table th { padding: 13px 12px; }
        .admin-companies-compact .companies-table th:first-child { width: 34%; }
        .admin-companies-compact .companies-table th:nth-child(2) { width: 22%; }
        .admin-companies-compact .companies-table th:nth-child(3) { width: 18%; }
        .admin-companies-compact .companies-table th:nth-child(4) { width: 14%; }
        .admin-companies-compact .companies-table th:last-child { width: 12%; }
        .admin-companies-compact .companies-table td { padding: 16px 12px; }
        .admin-companies-compact .companies-table td:first-child { padding-left: 18px; }
        .admin-companies-compact .companies-table tbody tr:hover { background: var(--surface-soft); }
        .admin-company-name { display: inline-flex; align-items: center; gap: 8px; }
        .admin-company-name svg { color: var(--cyan); flex-shrink: 0; }
        .admin-companies-compact .table-mono { color: var(--text-soft); font-size: 11px; }
        .admin-companies-compact .company-actions { display: flex; justify-content: flex-end; }
        .admin-companies-compact .actions-menu-toggle { width: 31px; height: 31px; }
        @media (max-width: 700px) {
            .admin-companies-compact .page-heading { align-items: flex-start; flex-direction: column; }
            .admin-companies-compact .companies-table thead { display: none; }
            .admin-companies-compact .companies-table, .admin-companies-compact .companies-table tbody, .admin-companies-compact .companies-table tr { display: block; width: 100%; }
            .admin-companies-compact .companies-table tr { padding: 14px 16px; border-top: 1px solid var(--border-soft); }
            .admin-companies-compact .companies-table td, .admin-companies-compact .companies-table td:first-child { display: block; width: 100%; padding: 7px 0; border: 0; }
            .admin-companies-compact .companies-table td:not(:first-child)::before { content: attr(data-label); display: block; margin-bottom: 4px; color: var(--text-muted); font-size: 9px; text-transform: uppercase; }
            .admin-companies-compact .company-actions { justify-content: flex-start; margin-top: 6px; }
        }
    </style>
    <div class="admin-companies-compact">
    <div class="page-heading page-heading-compact">
        <div>
            <h1>Empresas</h1>
            <p>{{ $empresas->total() }} cadastradas · {{ $empresasAtivas }} ativas</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('empresas.create') }}" class="button button-primary">+ Nova empresa</a>
        </div>
    </div>

    <div class="panel companies-page">
        @if ($empresas->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">+</div>
                <div>
                    <strong>Nenhuma empresa cadastrada</strong>
                    <span>Cadastre a primeira empresa para começar a vincular endpoints e fontes.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table companies-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Documento</th>
                            <th>Endpoints</th>
                            <th>Status</th>
                            <th class="table-actions-column"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($empresas as $empresa)
                            <tr>
                                <td><a href="{{ route('empresas.show', $empresa) }}" class="table-primary-link admin-company-name"><x-ui-icon name="building" size="18" />{{ $empresa->nome }}</a></td>
                                <td class="table-mono" data-label="Documento">{{ $empresa->documentoFormatado() ?? '-' }}</td>
                                <td class="table-mono" data-label="Endpoints">{{ $empresa->servidores_count }}</td>
                                <td data-label="Status">
                                    @if ($empresa->status === 'active')
                                        <span class="status-pill is-active">Ativa</span>
                                    @elseif ($empresa->status === 'pending')
                                        <span class="status-pill is-warning">Pendente de aprovação</span>
                                    @else
                                        <span class="status-pill is-inactive">Inativa</span>
                                    @endif
                                </td>
                                <td data-label="Ações">
                                    <div class="company-actions">
                                    <x-actions-menu label="Ações da empresa {{ $empresa->nome }}">
                                        <a href="{{ route('empresas.edit', $empresa) }}" class="actions-menu-item">Editar</a>
                                        <div class="actions-menu-divider"></div>
                                        <form action="{{ route('empresas.destroy', $empresa) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="actions-menu-item actions-menu-item-danger" onclick="return confirm('Remover empresa?')">Remover</button>
                                        </form>
                                    </x-actions-menu>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $empresas->links('pagination.compact') }}
    </div>
@endsection
