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

    @php
        $chartW = 900;
        $chartH = 220;
        $padL = 40;
        $padR = 12;
        $padT = 12;
        $padB = 28;
        $plotW = $chartW - $padL - $padR;
        $plotH = $chartH - $padT - $padB;

        $maxValor = collect($diario)->flatMap(fn ($d) => [$d['adicionados'], $d['removidos']])->max() ?: 0;
        $maxEixo = $maxValor === 0 ? 1 : (int) ceil($maxValor / 5) * 5;

        $n = max(count($diario), 1);
        $groupW = $plotW / $n;
        $barW = min(24, ($groupW - 4) / 2);

        $yFor = fn ($valor) => $padT + $plotH - ($maxEixo > 0 ? ($valor / $maxEixo) * $plotH : 0);

        $mostrarLabelDia = fn ($i) => $n <= 10 || $i % (int) ceil($n / 10) === 0;
    @endphp

    <div class="panel" style="margin-bottom:14px">
        <div class="panel-header">
            <h2>Domínios por dia</h2>
            <div style="display:flex;gap:14px;align-items:center;font-size:11px;color:var(--text-muted)">
                <span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:2px;background:#1f9d73;display:inline-block"></span>Adicionados</span>
                <span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:2px;background:#b87b28;display:inline-block"></span>Removidos</span>
            </div>
        </div>
        <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" style="width:100%;height:auto;overflow:visible" role="img" aria-label="Domínios adicionados e removidos por dia">
            {{-- gridlines --}}
            @for ($g = 0; $g <= 5; $g++)
                @php $gv = ($maxEixo / 5) * $g; @endphp
                <line x1="{{ $padL }}" y1="{{ $yFor($gv) }}" x2="{{ $chartW - $padR }}" y2="{{ $yFor($gv) }}" stroke="var(--border)" stroke-width="1" />
                <text x="{{ $padL - 8 }}" y="{{ $yFor($gv) + 3 }}" text-anchor="end" font-size="10" fill="var(--text-muted)">{{ number_format($gv, 0, ',', '.') }}</text>
            @endfor

            {{-- baseline --}}
            <line x1="{{ $padL }}" y1="{{ $padT + $plotH }}" x2="{{ $chartW - $padR }}" y2="{{ $padT + $plotH }}" stroke="var(--border)" stroke-width="1" />

            {{-- barras --}}
            @foreach ($diario as $i => $d)
                @php
                    $gx = $padL + $i * $groupW;
                    $xAdd = $gx + ($groupW / 2) - $barW - 1;
                    $xRem = $gx + ($groupW / 2) + 1;
                    $yAdd = $yFor($d['adicionados']);
                    $yRem = $yFor($d['removidos']);
                    $hAdd = ($padT + $plotH) - $yAdd;
                    $hRem = ($padT + $plotH) - $yRem;
                @endphp
                @if ($d['adicionados'] > 0)
                    <rect x="{{ $xAdd }}" y="{{ $yAdd }}" width="{{ $barW }}" height="{{ $hAdd }}" rx="4" fill="#1f9d73">
                        <title>{{ \Illuminate\Support\Carbon::parse($d['dia'])->format('d/m/Y') }} — {{ $d['adicionados'] }} adicionado(s)</title>
                    </rect>
                @endif
                @if ($d['removidos'] > 0)
                    <rect x="{{ $xRem }}" y="{{ $yRem }}" width="{{ $barW }}" height="{{ $hRem }}" rx="4" fill="#b87b28">
                        <title>{{ \Illuminate\Support\Carbon::parse($d['dia'])->format('d/m/Y') }} — {{ $d['removidos'] }} removido(s)</title>
                    </rect>
                @endif
                @if ($mostrarLabelDia($i))
                    <text x="{{ $gx + $groupW / 2 }}" y="{{ $chartH - 8 }}" text-anchor="middle" font-size="10" fill="var(--text-muted)">{{ \Illuminate\Support\Carbon::parse($d['dia'])->format('d/m') }}</text>
                @endif
            @endforeach
        </svg>
        <p style="color:var(--text-muted);font-size:10px;margin-top:8px">Passe o mouse numa barra pra ver o valor exato. Sem barra = sem mudança naquele dia.</p>
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
