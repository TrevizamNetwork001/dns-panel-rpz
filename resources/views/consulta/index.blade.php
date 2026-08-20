@extends('layouts.app')

@section('title', 'Consulta de domínio')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Ferramentas</div>
            <h1>Consulta de domínio</h1>
            <p>Verifique se um domínio está bloqueado e em qual lista.</p>
        </div>
    </div>

    <div class="panel">
        <form action="{{ route('consulta.index') }}" method="GET" style="display:flex;gap:10px">
            <input type="text" name="dominio" class="form-control" placeholder="exemplo.com ou www.exemplo.com" value="{{ $termoOriginal }}" autofocus>
            <button type="submit" class="button button-primary">Consultar</button>
        </form>
    </div>

    @if ($termo !== null)
        <div class="panel">
            <div class="panel-header"><h2>Resultado para "{{ $termo }}"</h2></div>

            @if ($resultados->isEmpty())
                <div class="empty-state">
                    <span class="status-pill is-active" style="margin-right:8px">Livre</span>
                    <span>Não encontrado em nenhuma lista visível. Não está sendo bloqueado.</span>
                </div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Lista</th>
                                <th>Empresa</th>
                                <th>Como bate</th>
                                <th>Entrada cadastrada</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resultados as $r)
                                <tr>
                                    <td><a href="{{ route('listas.show', $r['lista']) }}" class="table-primary-link">{{ $r['lista']->nome }}</a></td>
                                    <td>{{ $r['lista']->empresa->nome ?? 'Catálogo (todas)' }}</td>
                                    <td>
                                        <span class="status-pill is-inactive">{{ $r['tipo'] === 'exato' ? 'Bloqueado' : 'Bloqueado' }}</span>
                                        <span style="color:var(--text-muted);font-size:10px;margin-left:6px">{{ $r['tipo'] }}</span>
                                    </td>
                                    <td class="table-mono">{{ $r['dominio_cadastrado'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
@endsection
