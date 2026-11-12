@extends('layouts.app')

@section('title', 'Histórico — ' . $lista->nome)

@section('content')
    @include('listas.partials.admin-detail-styles')
    <div class="source-history">
    <nav class="source-breadcrumb" aria-label="Navegação"><a href="{{ route('listas.index') }}">Fontes</a><x-ui-icon name="chevron" size="13" /><a href="{{ route('listas.show', $lista) }}">{{ $lista->nome }}</a><x-ui-icon name="chevron" size="13" /><span>Histórico</span></nav>
    <div class="page-heading">
        <div>
            <div class="page-eyebrow"><x-ui-icon name="clock" size="14" />Evolução da fonte</div>
            <h1>Histórico de alterações</h1>
            <p>{{ $lista->nome }} · acompanhe os domínios adicionados e desativados.</p>
        </div>
        <form action="{{ route('listas.historico', $lista) }}" method="GET" class="source-period-filter">
            <label for="periodo">Período</label><div><select class="form-control" id="periodo" name="periodo" onchange="this.form.requestSubmit()">
                <option value="hoje" @selected($periodo === 'hoje')>Hoje</option><option value="7dias" @selected($periodo === '7dias')>Últimos 7 dias</option><option value="30dias" @selected($periodo === '30dias')>Últimos 30 dias</option>
            </select><button type="submit" class="button button-secondary">Exibir</button></div>
        </form>
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

        // Escala logaritmica (log1p): um pico isolado de import inicial (dezenas
        // de milhares de dominios num unico dia) nao pode esmagar os outros dias
        // a zero visual numa escala linear. log1p aceita valor 0 sem quebrar.
        $axisMax = max($maxValor, 1);
        $gridSteps = min(5, $axisMax);
        $logMax = log(1 + $axisMax);
        $usaEscalaLog = $maxValor > 0 && ($maxValor / max(1, collect($diario)->flatMap(fn ($d) => [$d['adicionados'], $d['removidos']])->filter(fn ($v) => $v > 0)->min() ?? 1)) > 20;

        $n = max(count($diario), 1);
        $groupW = $plotW / $n;
        $barW = min(24, ($groupW - 4) / 2);

        $yFor = $usaEscalaLog
            ? fn ($valor) => $padT + $plotH - ($logMax > 0 ? (log(1 + max($valor, 0)) / $logMax) * $plotH : 0)
            : fn ($valor) => $padT + $plotH - (($valor / $axisMax) * $plotH);

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
        <div class="source-chart-scroll" tabindex="0" aria-label="Gráfico por dia. Role horizontalmente para consultar todas as datas."><svg id="historico-chart" viewBox="0 0 {{ $chartW }} {{ $chartH }}" style="width:100%;height:auto;overflow:visible" role="img" aria-label="Domínios adicionados e removidos por dia">
            {{-- gridlines --}}
            @for ($g = 0; $g <= $gridSteps; $g++)
                @php
                    $frac = $g / $gridSteps;
                    $gy = $padT + $plotH - $frac * $plotH;
                    $gv = $usaEscalaLog
                        ? (int) round(exp($logMax * $frac) - 1)
                        : (int) round($axisMax * $frac);
                @endphp
                <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $chartW - $padR }}" y2="{{ $gy }}" stroke="var(--border)" stroke-width="1" />
                <text x="{{ $padL - 8 }}" y="{{ $gy + 3 }}" text-anchor="end" font-size="12" fill="var(--text-muted)">{{ number_format($gv, 0, ',', '.') }}</text>
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
                    <text x="{{ $gx + $groupW / 2 }}" y="{{ $chartH - 8 }}" text-anchor="middle" font-size="12" fill="var(--text-muted)">{{ \Illuminate\Support\Carbon::parse($d['dia'])->format('d/m') }}</text>
                @endif
            @endforeach
        </svg></div>
        <p class="source-mobile-hint">Deslize o gráfico para consultar todas as datas.</p>
        <p class="source-help">Passe o mouse numa barra pra ver o valor exato. Sem barra = sem mudança naquele dia.@if ($usaEscalaLog) Escala logarítmica — dias com poucas mudanças ficam maiores do que a proporção real com um pico isolado (ex: import inicial da lista).@endif</p>
    </div>

    @if (! $mostrarDetalhe)
        <div class="panel" style="margin-bottom:14px">
            <div class="alert-error" style="margin:0;background:rgba(33,199,232,0.08);border-color:var(--cyan, #21c7e8);color:var(--text)">
                Muitas mudanças nesse período ({{ number_format($adicionadosCount + $removidosCount, 0, ',', '.') }} no total) pra listar domínio por domínio — mostrando só o resumo acima. Normal em listas grandes (feeds de threat intel) com atualização automática.
            </div>
        </div>
    @endif

    @if ($mostrarDetalhe)
    <div class="source-history-columns">
        <section class="panel source-history-results">
            <div class="panel-header">
                <h2>Adicionados</h2>
                <span class="status-pill is-active">{{ $adicionados->count() }}</span>
            </div>
            @if ($adicionados->isEmpty())
                <div class="empty-state"><span>Nenhum domínio adicionado nesse período.</span></div>
            @else
                <ul class="source-history-events is-added" aria-label="Domínios adicionados">
                    @foreach ($adicionados as $dominio)
                        <li><span class="source-event-icon"><x-ui-icon name="plus" size="14" /></span><div><strong>{{ $dominio->dominio }}</strong><time datetime="{{ $dominio->created_at->toIso8601String() }}">Adicionado em {{ $dominio->created_at->format('d/m/Y H:i:s') }}</time></div></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="panel source-history-results">
            <div class="panel-header">
                <h2>Removidos</h2>
                <span class="status-pill is-warning">{{ $removidos->count() }}</span>
            </div>
            <p class="source-help">@if (auth()->user()->isAdmin())"Removido" significa desativado: o domínio deixou de bloquear por esta fonte. O registro permanece no histórico.@else"Removido" aqui significa que o domínio deixou de ser bloqueado por esta lista.@endif</p>
            @if ($removidos->isEmpty())
                <div class="empty-state"><span>Nenhum domínio removido nesse período.</span></div>
            @else
                <ul class="source-history-events is-removed" aria-label="Domínios removidos">
                    @foreach ($removidos as $dominio)
                        <li><span class="source-event-icon"><x-ui-icon name="x" size="14" /></span><div><strong>{{ $dominio->dominio }}</strong><time datetime="{{ $dominio->updated_at->toIso8601String() }}">Desativado em {{ $dominio->updated_at->format('d/m/Y H:i:s') }}</time></div></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
    @endif
    </div>
@endsection
