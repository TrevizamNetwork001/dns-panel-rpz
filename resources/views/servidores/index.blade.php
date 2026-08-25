@extends('layouts.app')

@section('title', 'Endpoints RPZ')

@section('content')
    <div class="page-heading page-heading-compact">
        <div>
            <h1>Endpoints RPZ</h1>
            <p>{{ $servidores->total() }} cadastrados · {{ $servidoresAtivos }} ativos</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('servidores.create') }}" class="button button-primary">+ Novo endpoint</a>
        </div>
    </div>

    <div class="panel">
        @if ($servidores->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">+</div>
                <div>
                    <strong>Nenhum endpoint cadastrado</strong>
                    <span>Cadastre o primeiro endpoint RPZ para gerar o token do zonefile.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Empresa</th>
                            <th>Fontes habilitadas</th>
                            <th>Última consulta</th>
                            <th>Status</th>
                            <th class="table-actions-column"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($servidores as $servidor)
                            @php $dias = $servidor->diasSemSincronizar(); @endphp
                            <tr>
                                <td><a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link">{{ $servidor->nome }}</a></td>
                                <td>{{ $servidor->empresa->nome }}</td>
                                <td>
                                    @if ($servidor->listas->isEmpty())
                                        <a href="{{ route('servidores.show', $servidor) }}" class="inline-link" style="color:var(--danger)">nenhuma — escolher</a>
                                    @else
                                        <a href="{{ route('servidores.show', $servidor) }}" class="inline-link" title="{{ $servidor->listas->pluck('nome')->join(', ') }}">{{ $servidor->listas->count() }} {{ $servidor->listas->count() === 1 ? 'fonte' : 'fontes' }}</a>
                                    @endif
                                </td>
                                <td class="table-mono">
                                    <div>{{ optional($servidor->last_synced_at)->format('d/m/Y H:i') ?? 'nunca' }}</div>
                                    @if ($dias === null)
                                        <span class="status-pill is-inactive" style="margin-top:4px">Sem consulta</span>
                                    @elseif ($dias >= 2)
                                        <span class="status-pill is-warning" style="margin-top:4px">Sem consulta há {{ $dias }} dias</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="status-pill @if($servidor->status === 'active') is-active @else is-inactive @endif">
                                        {{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td>
                                    <x-actions-menu label="Ações do endpoint {{ $servidor->nome }}">
                                        <a href="{{ route('servidores.edit', $servidor) }}" class="actions-menu-item">Editar</a>
                                        <div class="actions-menu-divider"></div>
                                        <form action="{{ route('servidores.destroy', $servidor) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="actions-menu-item actions-menu-item-danger" onclick="return confirm('Remover endpoint?')">Remover</button>
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

    {{ $servidores->links() }}
@endsection
