@extends('layouts.app')

@section('title', 'Licenças')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Gestão</div>
            <h1>Licenças</h1>
            <p>Licenças ativas por empresa.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('licencas.create') }}" class="button button-primary">+ Nova licença</a>
        </div>
    </div>

    <div class="panel">
        @if ($licencas->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">+</div>
                <div>
                    <strong>Nenhuma licença cadastrada</strong>
                    <span>Cadastre a primeira licença para liberar o cadastro de servidores da empresa.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Início</th>
                            <th>Expiração</th>
                            <th>Máx. servidores</th>
                            <th>Status</th>
                            <th class="table-actions-column"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($licencas as $licenca)
                            <tr>
                                <td>{{ $licenca->empresa->nome }}</td>
                                <td>{{ $licenca->starts_at->format('d/m/Y') }}</td>
                                <td>{{ optional($licenca->expires_at)->format('d/m/Y') ?? '-' }}</td>
                                <td>{{ $licenca->max_servidores }}</td>
                                <td>
                                    <span class="status-pill @if($licenca->status === 'active') is-active @else is-inactive @endif">
                                        {{ $licenca->status }}
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="{{ route('licencas.edit', $licenca) }}" class="table-action-link">Editar</a>
                                        <form action="{{ route('licencas.destroy', $licenca) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="table-action-link" style="background:none;border:0" onclick="return confirm('Remover licença?')">Remover</button>
                                        </form>
                                    </div>
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
