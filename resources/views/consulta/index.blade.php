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
        <p style="color:var(--text-muted);font-size:10px;margin:10px 0 0">A consulta principal é por domínio completo (é assim que o DNS resolve de verdade). Se você digitar só um pedaço, mostramos sugestões de domínios cadastrados que contêm esse trecho.</p>
    </div>

    @if ($termo !== null)
        <div class="panel">
            <div class="panel-header"><h2>Resultado para "{{ $termo }}"</h2></div>

            @if ($resultados->isEmpty())
                <div class="empty-state">
                    <span class="status-pill is-active" style="margin-right:8px">Livre</span>
                    <span>Não encontrado em nenhuma lista visível (match exato ou por wildcard). Não está sendo bloqueado.</span>
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
                                        <span class="status-pill is-inactive">Bloqueado</span>
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

        @if ($resultados->isEmpty() && $sugestoes->isNotEmpty())
            <div class="panel">
                <div class="panel-header"><h2>Domínios cadastrados parecidos com "{{ $termoOriginal }}"</h2></div>
                <p style="color:var(--text-muted);font-size:10px;margin:0 0 12px">Busca por trecho — nenhum desses necessariamente está bloqueando "{{ $termo }}", são só domínios cadastrados que contêm esse texto.</p>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Domínio</th>
                                <th>Lista</th>
                                <th>Empresa</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sugestoes as $d)
                                <tr>
                                    <td class="table-mono">{{ $d->dominio }}</td>
                                    <td><a href="{{ route('listas.show', $d->lista) }}" class="table-primary-link">{{ $d->lista->nome }}</a></td>
                                    <td>{{ $d->lista->empresa->nome ?? 'Catálogo (todas)' }}</td>
                                    <td>
                                        <span class="status-pill @if($d->ativo) is-active @else is-inactive @endif">{{ $d->ativo ? 'Ativo' : 'Inativo' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@endsection
