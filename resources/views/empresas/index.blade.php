@extends('layouts.app')

@section('title', 'Empresas')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Gestão</div>
            <h1>Empresas</h1>
            <p>Provedores cadastrados no painel.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('empresas.create') }}" class="button button-primary">+ Nova empresa</a>
        </div>
    </div>

    <div class="panel">
        @if ($empresas->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">+</div>
                <div>
                    <strong>Nenhuma empresa cadastrada</strong>
                    <span>Cadastre a primeira empresa para começar a vincular servidores e listas.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Documento</th>
                            <th>Status</th>
                            <th class="table-actions-column"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($empresas as $empresa)
                            <tr>
                                <td><a href="{{ route('empresas.show', $empresa) }}" class="table-primary-link">{{ $empresa->nome }}</a></td>
                                <td class="table-mono">{{ $empresa->documento ?? '-' }}</td>
                                <td>
                                    @if ($empresa->status === 'active')
                                        <span class="status-pill is-active">Ativa</span>
                                    @elseif ($empresa->status === 'pending')
                                        <span class="status-pill is-inactive">Pendente de aprovação</span>
                                    @else
                                        <span class="status-pill is-inactive">Inativa</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="{{ route('empresas.edit', $empresa) }}" class="table-action-link">Editar</a>
                                        <form action="{{ route('empresas.destroy', $empresa) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="table-action-link" style="background:none;border:0" onclick="return confirm('Remover empresa?')">Remover</button>
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

    {{ $empresas->links() }}
@endsection
