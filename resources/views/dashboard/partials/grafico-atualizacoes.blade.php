@php
    $n = count($pontos);
    $totalPeriodo = array_sum(array_column($pontos, 'total'));
    $maximo = max(array_column($pontos, 'total'));
    $topo = max(4, (int) (ceil($maximo / 4) * 4));
    $w = 720; $h = 190; $esq = 44; $dir = 16; $cima = 10; $baixo = 28;
    $larg = $w - $esq - $dir;
    $alt = $h - $cima - $baixo;
    $px = fn (int $i) => $esq + ($n > 1 ? $larg * $i / ($n - 1) : 0);
    $py = fn (int $v) => $cima + $alt - ($alt * $v / $topo);
    $linha = collect($pontos)->map(fn ($p, $i) => round($px($i), 1).','.round($py($p['total']), 1))->implode(' ');
    $resumo = 'Atualizações dos servidores nos últimos '.$n.' dias: '.$totalPeriodo.' no total, máximo de '.$maximo.' em um dia.';
@endphp
<div class="panel" id="grafico-atualizacoes" style="margin:10px 0">
    <div class="panel-header">
        <h2>Atualizações de servidores</h2>
        <span class="status-pill is-muted">Últimos {{ $n }} dias</span>
    </div>
    @if ($totalPeriodo === 0)
        <div class="empty-state"><span>Nenhuma atualização nos últimos {{ $n }} dias — o Unbound dos seus servidores ainda não buscou a lista.</span></div>
    @else
        <p style="color:var(--text-muted);font-size:11px;margin:0 0 5px">Cada ponto é quantas vezes os seus servidores buscaram a lista naquele dia. Total no período: <strong>{{ number_format($totalPeriodo, 0, ',', '.') }}</strong>.</p>
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
            <polyline points="{{ $linha }}" style="fill:none;stroke:var(--cyan);stroke-width:2;stroke-linejoin:round;stroke-linecap:round"/>
            @foreach ($pontos as $i => $p)
                <circle cx="{{ round($px($i), 1) }}" cy="{{ round($py($p['total']), 1) }}" r="3" style="fill:var(--cyan)"><title>{{ $p['data']->format('d/m/Y') }}: {{ $p['total'] }} {{ $p['total'] === 1 ? 'atualização' : 'atualizações' }}</title></circle>
            @endforeach
        </svg>
    @endif
</div>
