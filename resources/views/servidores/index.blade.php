@extends('layouts.app')

@section('title', 'Servidores')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Gestão</div>
            <h1>Servidores</h1>
            <p>Servidores Unbound vinculados aos provedores.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('servidores.create') }}" class="button button-primary">+ Novo servidor</a>
        </div>
    </div>

    <div class="panel">
        @if ($servidores->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">+</div>
                <div>
                    <strong>Nenhum servidor cadastrado</strong>
                    <span>Cadastre o primeiro servidor Unbound para gerar o token do zonefile RPZ.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Empresa</th>
                            <th>Listas</th>
                            <th>Status</th>
                            <th>Última sincronização</th>
                            <th class="table-actions-column"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($servidores as $servidor)
                            <tr>
                                <td><a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link">{{ $servidor->nome }}</a></td>
                                <td>{{ $servidor->empresa->nome }}</td>
                                <td>
                                    @if ($servidor->listas->isEmpty())
                                        <a href="{{ route('servidores.show', $servidor) }}" class="inline-link" style="color:var(--danger)">nenhuma — escolher</a>
                                    @else
                                        <a href="{{ route('servidores.show', $servidor) }}" class="inline-link">{{ $servidor->listas->pluck('nome')->implode(', ') }}</a>
                                    @endif
                                </td>
                                <td>
                                    <span class="status-pill @if($servidor->status === 'active') is-active @else is-inactive @endif">
                                        {{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="table-mono">
                                    {{ optional($servidor->last_synced_at)->format('d/m/Y H:i') ?? 'nunca' }}
                                    @php $dias = $servidor->diasSemSincronizar(); @endphp
                                    @if ($dias === null)
                                        <span class="status-pill is-inactive" style="margin-left:6px">sem sync</span>
                                    @elseif ($dias >= 2)
                                        <span class="status-pill is-warning" style="margin-left:6px">{{ $dias }}d sem sync</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="{{ route('servidores.edit', $servidor) }}" class="table-action-link">Editar</a>
                                        <form action="{{ route('servidores.destroy', $servidor) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="table-action-link" style="background:none;border:0" onclick="return confirm('Remover servidor?')">Remover</button>
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

    {{ $servidores->links() }}
@endsection
