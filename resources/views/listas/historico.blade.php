@extends('layouts.app')

@section('title', 'Histórico — ' . $lista->nome)

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Lista</div>
            <h1>Histórico de alterações</h1>
            <p><a href="{{ route('listas.show', $lista) }}" class="inline-link">&larr; {{ $lista->nome }}</a></p>
        </div>
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon cyan">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($totalAtivos, 0, ',', '.') }}</div>
            <div class="metric-label">Domínios ativos agora</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon green">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($adicionadosCount, 0, ',', '.') }}</div>
            <div class="metric-label">Adicionados no período</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon amber">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ number_format($removidosCount, 0, ',', '.') }}</div>
            <div class="metric-label">Removidos no período</div>
        </div>
    </div>

    @if (! $mostrarDetalhe)
        <div class="panel" style="margin-bottom:14px">
            <div class="alert-error" style="margin:0;background:rgba(33,199,232,0.08);border-color:var(--cyan, #21c7e8);color:var(--text)">
                Muitas mudanças nesse período ({{ number_format($adicionadosCount + $removidosCount, 0, ',', '.') }} no total) pra listar domínio por domínio — mostrando só o resumo acima. Normal em listas grandes (feeds de threat intel) com atualização automática.
            </div>
        </div>
    @endif

    <div class="panel" style="margin-bottom:14px">
        <form action="{{ route('listas.historico', $lista) }}" method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div class="field-group" style="margin:0;min-width:200px">
                <label for="periodo">Período</label>
                <select class="form-control" id="periodo" name="periodo" onchange="this.form.submit()">
                    <option value="hoje" @selected($periodo === 'hoje')>Hoje</option>
                    <option value="7dias" @selected($periodo === '7dias')>Últimos 7 dias</option>
                    <option value="30dias" @selected($periodo === '30dias')>Últimos 30 dias</option>
                </select>
            </div>
        </form>
    </div>

    @if ($mostrarDetalhe)
    <div class="details-grid">
        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Adicionados</h2>
                <span class="status-pill is-active">{{ $adicionados->count() }}</span>
            </div>
            @if ($adicionados->isEmpty())
                <div class="empty-state"><span>Nenhum domínio adicionado nesse período.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Domínio</th><th>Adicionado em</th></tr></thead>
                        <tbody>
                            @foreach ($adicionados as $dominio)
                                <tr>
                                    <td class="table-mono">{{ $dominio->dominio }}</td>
                                    <td class="table-mono">{{ $dominio->created_at->format('d/m/Y H:i:s') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Removidos</h2>
                <span class="status-pill is-warning">{{ $removidos->count() }}</span>
            </div>
            <p style="color:var(--text-muted);font-size:11px;margin:0 0 10px">"Removido" aqui significa desativado — o domínio saiu do zonefile, mas o registro fica guardado (não é apagado do banco).</p>
            @if ($removidos->isEmpty())
                <div class="empty-state"><span>Nenhum domínio removido nesse período.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Domínio</th><th>Removido em</th></tr></thead>
                        <tbody>
                            @foreach ($removidos as $dominio)
                                <tr>
                                    <td class="table-mono">{{ $dominio->dominio }}</td>
                                    <td class="table-mono">{{ $dominio->updated_at->format('d/m/Y H:i:s') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    @endif
@endsection
