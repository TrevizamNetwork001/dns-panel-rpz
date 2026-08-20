@extends('layouts.app')

@section('title', $servidor->nome)

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Servidor</div>
            <h1>{{ $servidor->nome }}</h1>
            <p><a href="{{ route('empresas.show', $servidor->empresa) }}" class="inline-link">{{ $servidor->empresa->nome }}</a></p>
        </div>
        <div class="page-actions">
            <a href="{{ route('servidores.edit', $servidor) }}" class="button button-secondary">Editar</a>
        </div>
    </div>

    <div class="details-grid">
        <div class="panel">
            <div class="panel-header"><h2>Status</h2></div>
            <span class="status-pill @if($servidor->status === 'active') is-active @else is-inactive @endif">
                {{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }}
            </span>
            <dl class="details-list" style="margin-top:14px">
                <div><dt>Última sincronização</dt><dd>{{ optional($servidor->last_synced_at)->format('d/m/Y H:i:s') ?? 'nunca' }}</dd></div>
            </dl>
        </div>

        <div class="panel">
            <div class="panel-header"><h2>Zonefile RPZ</h2></div>
            <p style="color:var(--text-muted);font-size:11px;margin:0 0 10px">URL usada pelo Unbound (<code>rpz:</code> + <code>url:</code>):</p>
            <code style="word-break:break-all">{{ url('/rpz/' . $servidor->token . '.zone') }}</code>
        </div>

        <div class="panel details-card-wide">
            <div class="panel-header"><h2>Listas vinculadas ({{ $servidor->listas->count() }})</h2></div>
            @if ($servidor->listas->isEmpty())
                <div class="empty-state"><span>Nenhuma lista vinculada ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Lista</th><th>Tipo</th><th class="table-actions-column"></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($servidor->listas as $lista)
                                <tr>
                                    <td><a href="{{ route('listas.show', $lista) }}" class="table-primary-link">{{ $lista->nome }}</a></td>
                                    <td>
                                        <span class="status-pill is-inactive">{{ $lista->empresa_id ? 'Própria' : 'Catálogo' }}</span>
                                    </td>
                                    <td>
                                        <form action="{{ route('servidores.listas.detach', [$servidor, $lista]) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="table-action-link" style="background:none;border:0" onclick="return confirm('Remover esta lista do servidor?')">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel details-card-wide">
            <div class="panel-header"><h2>Listas disponíveis para adicionar</h2></div>
            @if ($listasDisponiveis->isEmpty())
                <div class="empty-state"><span>Nenhuma lista disponível no momento.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Lista</th><th>Tipo</th><th class="table-actions-column"></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($listasDisponiveis as $lista)
                                <tr>
                                    <td>{{ $lista->nome }}</td>
                                    <td>
                                        <span class="status-pill is-inactive">{{ $lista->empresa_id ? 'Própria' : 'Catálogo' }}</span>
                                    </td>
                                    <td>
                                        <form action="{{ route('servidores.listas.attach', [$servidor, $lista]) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="table-action-link">Adicionar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
