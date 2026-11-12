@php
    $n = count($pontos);
    $seriesVisiveis = $seriesDns->filter(fn ($serie) => $dnsSelecionado === null || $serie['id'] === $dnsSelecionado)->values();
    $totalPeriodo = $seriesVisiveis->sum(fn ($serie) => array_sum(array_column($serie['pontos'], 'total')));
    $maximo = $seriesVisiveis->max(fn ($serie) => max(array_column($serie['pontos'], 'total'))) ?? 0;
    $topo = max(4, (int) (ceil($maximo / 4) * 4));
    $w = 720; $h = 190; $esq = 44; $dir = 16; $cima = 10; $baixo = 28;
    $larg = $w - $esq - $dir;
    $alt = $h - $cima - $baixo;
    $px = fn (int $i) => $esq + ($n > 1 ? $larg * $i / ($n - 1) : 0);
    $py = fn (int $v) => $cima + $alt - ($alt * $v / $topo);
    $resumo = 'Sincronizações dos servidores nos últimos '.$n.' dias: '.$totalPeriodo.' no total, máximo de '.$maximo.' em um dia.';
@endphp
<div class="panel" id="grafico-atualizacoes" style="margin:10px 0">
    <div class="panel-header">
        <h2>Sincronizações por dia</h2>
        <span class="status-pill is-muted">Últimos {{ $n }} dias</span>
    </div>
    @if ($seriesDns->isNotEmpty())
        <form method="GET" action="{{ route('dashboard') }}" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:14px">
            <select id="grafico-dns" name="dns" aria-label="Selecionar DNS do gráfico" class="form-control" style="width:auto;max-width:100%;min-width:0;min-height:34px;padding:6px 28px 6px 10px;border-radius:8px;font-size:12px" onchange="this.form.requestSubmit()">
                <option value="">Todos os DNS</option>
                @foreach ($seriesDns as $serie)
                    <option value="{{ $serie['id'] }}" @selected($dnsSelecionado === $serie['id'])>{{ $serie['nome'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="button button-secondary" style="min-height:34px;padding:6px 12px;font-size:12px">Exibir</button>
        </form>
        <ul aria-label="Legenda dos DNS" style="display:flex;flex-wrap:wrap;gap:12px;list-style:none;padding:0;margin:0 0 12px">
            @foreach ($seriesVisiveis as $serie)
                <li style="display:flex;align-items:center;gap:6px;font-size:12px"><span aria-hidden="true" style="width:12px;height:12px;border-radius:50%;background:{{ $serie['cor'] }}"></span>{{ $serie['nome'] }}</li>
            @endforeach
        </ul>
    @endif
    @if ($totalPeriodo === 0)
        <div class="empty-state"><span>Nenhuma atualização nos últimos {{ $n }} dias — nenhuma busca registrada para os DNS selecionados.</span></div>
    @else
        <p style="color:var(--text-muted);font-size:11px;margin:0 0 5px">Buscas por dia, com uma linha para cada DNS selecionado. Total no período: <strong>{{ number_format($totalPeriodo, 0, ',', '.') }}</strong>.</p>
        <svg viewBox="0 0 {{ $w }} {{ $h }}" width="100%" role="img" aria-label="{{ $resumo }}" style="display:block;max-width:100%;height:auto">
            <title>{{ $resumo }}</title>
            @for ($t = 0; $t <= 4; $t++)
                @php $valor = (int) round($topo * $t / 4); $yy = round($py($valor), 1); @endphp
                <line x1="{{ $esq }}" x2="{{ $w - $dir }}" y1="{{ $yy }}" y2="{{ $yy }}" style="stroke:var(--border);stroke-width:1"/>
                <text x="{{ $esq - 8 }}" y="{{ $yy + 4 }}" text-anchor="end" style="fill:var(--text-muted);font-size:11px">{{ $valor }}</text>
            @endfor
            @foreach ($pontos as $i => $p)
                @if ($i % 5 === 0 || $i === $n - 1)
                    <text x="{{ round($px($i), 1) }}" y="{{ $h - 12 }}" text-anchor="middle" style="fill:var(--text-muted);font-size:11px">{{ $p['data']->format('d/m') }}</text>
                @endif
            @endforeach
            @foreach ($seriesVisiveis as $serie)
                @php $linha = collect($serie['pontos'])->map(fn ($p, $i) => round($px($i), 1).','.round($py($p['total']), 1))->implode(' '); @endphp
                <g data-dns-id="{{ $serie['id'] }}">
                    <polyline points="{{ $linha }}" style="fill:none;stroke:{{ $serie['cor'] }};stroke-width:2;stroke-linejoin:round;stroke-linecap:round" @if ($loop->index > 0) stroke-dasharray="{{ 5 + $loop->index * 2 }} 3" @endif><title>{{ $serie['nome'] }}</title></polyline>
                    @foreach ($serie['pontos'] as $i => $p)
                        <circle cx="{{ round($px($i), 1) }}" cy="{{ round($py($p['total']), 1) }}" r="3" style="fill:{{ $serie['cor'] }}"><title>{{ $serie['nome'] }} — {{ $p['data']->format('d/m/Y') }}: {{ $p['total'] }} {{ $p['total'] === 1 ? 'atualização' : 'atualizações' }}</title></circle>
                    @endforeach
                </g>
            @endforeach
        </svg>
    @endif
</div>
