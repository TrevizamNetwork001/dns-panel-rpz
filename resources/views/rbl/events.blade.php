@extends('layouts.app')
@section('title', 'Eventos RBL')
@section('content')
<div class="page-heading"><div><div class="page-eyebrow">Monitoramento</div><h1>Eventos RBL</h1><p>Eventos ativos em qualquer momento do período, filtrados pelo status atual. Datas no fuso {{ config('app.timezone') }}.</p></div></div>
@include('rbl.navigation')
<div class="panel form-panel"><form method="GET" action="{{ route('rbl.events') }}"><div class="form-grid">
@include('rbl.period')
<div class="field-group"><label for="status">Status</label><select class="form-control" name="status" id="status">@foreach(['all' => 'Todos', 'open' => 'Abertos', 'resolved' => 'Resolvidos'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></div>
<div class="field-group"><label for="target">Alvo</label><select class="form-control" name="target" id="target"><option value="">Todos</option>@foreach($targets as $target)<option value="{{ $target->id }}" @selected(request('target') == $target->id)>{{ $target->name }} — {{ $target->value }}</option>@endforeach</select></div>
<div class="field-group"><label for="list">RBL</label><select class="form-control" name="list" id="list"><option value="">Todas</option>@foreach($lists as $list)<option value="{{ $list->id }}" @selected(request('list') == $list->id)>{{ $list->name }}</option>@endforeach</select></div>
</div><button class="button button-primary">Filtrar</button></form></div>
<div class="panel">@include('rbl.event-table'){{ $events->links() }}</div>
@endsection
