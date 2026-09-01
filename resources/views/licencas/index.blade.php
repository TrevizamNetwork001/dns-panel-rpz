@extends('layouts.app')

@section('title', 'Licenças')

@section('content')
    <div class="page-heading page-heading-compact">
        <div>
            <h1>Licenças</h1>
            <p>{{ $licencas->total() }} cadastradas</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('licencas.create') }}" class="button button-primary">+ Nova licença</a>
        </div>
    </div>

    <form method="GET" action="{{ route('licencas.index') }}" class="panel" style="margin-bottom:14px">
        <div class="field-group" style="max-width:260px">
            <label for="validity">Filtrar validade</label>
            <select class="form-control" id="validity" name="validity" onchange="this.form.submit()">
                <option value="all" @selected($filter === 'all')>Todas</option>
                <option value="active" @selected($filter === 'active')>Ativas</option>
                <option value="expired" @selected($filter === 'expired')>Vencidas</option>
                <option value="no_expiry" @selected($filter === 'no_expiry')>Sem vencimento</option>
                <option value="inactive" @selected($filter === 'inactive')>Inativas</option>
            </select>
        </div>
    </form>

    <div class="panel">
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
                <table class="data-table">
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
                                <td>{{ $licenca->empresa->nome }}</td>
                                <td>
                                    <div class="table-mono">{{ $licenca->starts_at->format('d/m/Y') }} → {{ optional($licenca->expires_at)->format('d/m/Y') ?? 'Sem vencimento' }}</div>
                                    @if ($licenca->expirationSummary() !== null)
                                        <span class="table-secondary-text">{{ $licenca->expirationSummary() }}</span>
                                    @endif
                                </td>
                                <td class="table-mono">{{ $usoAtual }} de {{ $licenca->max_servidores }} {{ $licenca->max_servidores === 1 ? 'endpoint utilizado' : 'endpoints utilizados' }}</td>
                                <td><span class="status-pill {{ $statusClasse }}">{{ $statusLabel }}</span></td>
                                <td>
                                    <x-actions-menu label="Ações da licença de {{ $licenca->empresa->nome }}">
                                        <a href="{{ route('licencas.edit', $licenca) }}" class="actions-menu-item">Editar</a>
                                        <div class="actions-menu-divider"></div>
                                        <form action="{{ route('licencas.destroy', $licenca) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="actions-menu-item actions-menu-item-danger" onclick="return confirm('Remover licença?')">Remover</button>
                                        </form>
                                    </x-actions-menu>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $licencas->links() }}
@endsection
