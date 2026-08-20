@extends('layouts.app')

@section('title', 'Listas')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Gestão</div>
            <h1>Listas</h1>
            <p>Listas de bloqueio (RPZ) por provedor.</p>
        </div>
        <div class="page-actions">
            @if (auth()->user()->isAdmin())
            <a href="{{ route('listas.create') }}" class="button button-primary">+ Nova lista</a>
            @endif
        </div>
    </div>

    <div class="panel">
        @if ($listas->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">+</div>
                <div>
                    <strong>Nenhuma lista cadastrada</strong>
                    <span>Cadastre a primeira lista de bloqueio para vincular aos servidores.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Empresa</th>
                            <th>Status</th>
                            <th class="table-actions-column"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listas as $lista)
                            <tr>
                                <td>
                                    <a href="{{ route('listas.show', $lista) }}" class="table-primary-link">{{ $lista->nome }}</a>
                                    @if ($lista->isExterna())
                                        <span class="status-pill is-info" style="margin-left:6px">externa</span>
                                    @endif
                                </td>
                                <td>{{ $lista->empresa?->nome ?? 'Catálogo (todas)' }}</td>
                                <td>
                                    <span class="status-pill @if($lista->status === 'active') is-active @else is-inactive @endif">
                                        {{ $lista->status === 'active' ? 'Ativa' : 'Inativa' }}
                                    </span>
                                </td>
                                <td>
                                    @if (auth()->user()->isAdmin())
                                    <div class="table-actions">
                                        <a href="{{ route('listas.edit', $lista) }}" class="table-action-link">Editar</a>
                                        @if ($lista->isExterna())
                                            <form action="{{ route('listas.toggle-sync', $lista) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="table-action-link" style="background:none;border:0">{{ $lista->sync_ativo ? 'Pausar sync' : 'Reativar sync' }}</button>
                                            </form>
                                        @else
                                            <form action="{{ route('listas.destroy', $lista) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="table-action-link" style="background:none;border:0" onclick="return confirm('Remover lista?')">Remover</button>
                                            </form>
                                        @endif
                                    </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $listas->links() }}
@endsection
