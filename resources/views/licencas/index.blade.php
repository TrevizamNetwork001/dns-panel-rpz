@extends('layouts.app')

@section('title', 'Licenças')

@section('content')
    <style>
        .admin-licenses-compact { max-width: 1400px; margin: 0 auto; }
        .admin-licenses-compact .page-heading { align-items: center; margin-bottom: 22px; }
        .admin-licenses-compact .page-heading h1 { font-size: 28px; letter-spacing: -.03em; }
        .admin-licenses-compact .page-heading p { color: var(--text-soft); font-size: 12px; }
        .admin-licenses-compact .page-heading .button { min-height: 40px; }
        .admin-licenses-compact .license-page { padding: 0; overflow: hidden; }
        .admin-licenses-compact .licenses-table { table-layout: fixed; }
        .admin-licenses-compact .licenses-table th { padding: 13px 12px; }
        .admin-licenses-compact .licenses-table th:first-child { width: 26%; }
        .admin-licenses-compact .licenses-table th:nth-child(2) { width: 25%; }
        .admin-licenses-compact .licenses-table th:nth-child(3) { width: 25%; }
        .admin-licenses-compact .licenses-table th:nth-child(4) { width: 14%; }
        .admin-licenses-compact .licenses-table th:last-child { width: 10%; }
        .admin-licenses-compact .licenses-table td { padding: 16px 12px; }
        .admin-licenses-compact .licenses-table td:first-child { padding-left: 18px; }
        .admin-licenses-compact .licenses-table tbody tr:hover { background: var(--surface-soft); }
        .admin-license-company { display: inline-flex; align-items: center; gap: 8px; color: var(--text); font-weight: 550; }
        .admin-license-company svg { color: var(--cyan); flex-shrink: 0; }
        .admin-licenses-compact .license-actions { display: flex; justify-content: flex-end; }
        .admin-licenses-compact .actions-menu-toggle { width: 31px; height: 31px; }
        @media (max-width: 700px) {
            .admin-licenses-compact .page-heading { align-items: flex-start; flex-direction: column; }
            .admin-licenses-compact .licenses-table thead { display: none; }
            .admin-licenses-compact .licenses-table, .admin-licenses-compact .licenses-table tbody, .admin-licenses-compact .licenses-table tr { display: block; width: 100%; }
            .admin-licenses-compact .licenses-table tr { padding: 14px 16px; border-top: 1px solid var(--border-soft); }
            .admin-licenses-compact .licenses-table td, .admin-licenses-compact .licenses-table td:first-child { display: block; width: 100%; padding: 7px 0; border: 0; }
            .admin-licenses-compact .licenses-table td:not(:first-child)::before { content: attr(data-label); display: block; margin-bottom: 4px; color: var(--text-muted); font-size: 9px; text-transform: uppercase; }
            .admin-licenses-compact .license-actions { justify-content: flex-start; margin-top: 6px; }
        }
    </style>
    <div class="admin-licenses-compact">
    <div class="page-heading page-heading-compact">
        <div>
            <h1>Licenças</h1>
            <p>{{ $licencas->total() }} cadastradas</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('licencas.create') }}" class="button button-primary">+ Nova licença</a>
        </div>
    </div>

    <div class="panel license-page">
        @if ($licencas->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">+</div>
                <div>
                    <strong>Nenhuma licença cadastrada</strong>
                    <span>Cadastre a primeira licença para liberar o cadastro de endpoints da empresa.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table licenses-table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Validade</th>
                            <th>Uso</th>
                            <th>Status</th>
                            <th class="table-actions-column"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($licencas as $licenca)
                            @php
                                $statusLabel = match ($licenca->status) {
                                    'active' => 'Ativa',
                                    'inactive' => 'Inativa',
                                    'expired' => 'Expirada',
                                    default => ucfirst($licenca->status),
                                };
                                $statusClasse = match ($licenca->status) {
                                    'active' => 'is-active',
                                    'expired' => 'is-inactive',
                                    default => 'is-muted',
                                };
                                $usoAtual = $licenca->empresa?->servidores_count ?? 0;
                            @endphp
                            <tr>
                                <td><span class="admin-license-company"><x-ui-icon name="building" size="18" />{{ $licenca->empresa->nome }}</span></td>
                                <td data-label="Validade">
                                    <div class="table-mono">{{ $licenca->starts_at->format('d/m/Y') }} → {{ optional($licenca->expires_at)->format('d/m/Y') ?? 'Sem vencimento' }}</div>
                                    @if ($licenca->expirationSummary() !== null)
                                        <span class="table-secondary-text">{{ $licenca->expirationSummary() }}</span>
                                    @endif
                                </td>
                                <td class="table-mono" data-label="Uso">{{ $usoAtual }} de {{ $licenca->max_servidores }} {{ $licenca->max_servidores === 1 ? 'endpoint utilizado' : 'endpoints utilizados' }}</td>
                                <td data-label="Status"><span class="status-pill {{ $statusClasse }}">{{ $statusLabel }}</span></td>
                                <td data-label="Ações"><div class="license-actions">
                                    <x-actions-menu label="Ações da licença de {{ $licenca->empresa->nome }}">
                                        <a href="{{ route('licencas.edit', $licenca) }}" class="actions-menu-item">Editar</a>
                                        <div class="actions-menu-divider"></div>
                                        <form action="{{ route('licencas.destroy', $licenca) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="actions-menu-item actions-menu-item-danger" onclick="return confirm('Remover licença?')">Remover</button>
                                        </form>
                                    </x-actions-menu></div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $licencas->links('pagination.compact') }}
    </div>
@endsection
