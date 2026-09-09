@extends('layouts.app')
@section('title', 'Relatório RBL')
@section('content')
<div class="page-heading"><div><div class="page-eyebrow">Monitoramento</div><h1>Relatório RBL</h1><p>Datas inclusivas no fuso {{ config('app.timezone') }}. Eventos abertos contabilizam primeiras detecções, mesmo que já resolvidos.</p></div></div>
@include('rbl.navigation')
<div class="panel form-panel"><form method="GET" action="{{ route('rbl.reports') }}"><div class="form-grid">@include('rbl.period')</div><button class="button button-primary">Filtrar</button> <button class="button" name="format" value="csv">Exportar CSV</button></form></div>
<div class="metrics-grid">@foreach($summary as $label => $value)<div class="metric-card"><div class="metric-label">{{ $label }}</div><div class="metric-value">{{ $value }}</div></div>@endforeach</div>
<div class="panel"><h2>Alvos mais recorrentes em blacklist</h2><p>Até 20 alvos, por quantidade de checks listed; um alvo pode aparecer em várias listas.</p><div class="table-responsive"><table class="data-table"><thead><tr><th>Alvo</th><th>Valor verificado</th><th>Ocorrências listed</th></tr></thead><tbody>@forelse($topTargets as $item)<tr><td>{{ $item->target?->name ?? '—' }}</td><td>{{ $item->checked_value }}</td><td>{{ $item->total }}</td></tr>@empty<tr><td colspan="3">Nenhuma ocorrência no período.</td></tr>@endforelse</tbody></table></div></div>
<div class="panel"><h2>RBLs com mais ocorrências</h2><p>Até 20 listas por quantidade de checks listed.</p><div class="table-responsive"><table class="data-table"><thead><tr><th>RBL</th><th>Ocorrências listed</th></tr></thead><tbody>@forelse($topLists as $item)<tr><td>{{ $item->list?->name ?? '—' }}</td><td>{{ $item->total }}</td></tr>@empty<tr><td colspan="2">Nenhuma ocorrência no período.</td></tr>@endforelse</tbody></table></div></div>
<div class="panel"><h2>Resumo por grupo</h2><div class="table-responsive"><table class="data-table">@foreach($groupSummary as $row)@if($loop->first)<thead><tr>@foreach(array_keys($row) as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>@endif<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@if($loop->last)</tbody>@endif
@endforeach</table></div></div>
@endsection
