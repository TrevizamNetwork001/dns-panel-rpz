@extends('layouts.app')
@section('title','Exclusões ANATEL')
@section('content')
<div class="page-heading"><div><div class="page-eyebrow">ANATEL</div><h1>Exclusões</h1><p>Domínio exato nunca é interpretado como regex.</p></div><a class="button button-secondary" href="{{ route('listas.show',$lista) }}">Voltar</a></div>
<div class="details-grid"><div class="panel"><h2>Nova exclusão</h2><form method="POST" action="{{ route('anatel.exclusions.store',$lista) }}">@csrf<label>Tipo</label><select class="form-control" name="type"><option value="exact">Domínio exato</option><option value="regex">Regex explícita</option></select><label>Valor</label><input class="form-control" name="value" required><label>Observação</label><input class="form-control" name="notes"><button class="button button-primary">Salvar</button></form></div>
<div class="panel"><h2>Importar exclude_list antigo</h2><form method="POST" enctype="multipart/form-data" action="{{ route('anatel.exclusions.legacy',$lista) }}">@csrf<input class="form-control" type="file" name="exclude_list" required><button class="button button-secondary">Importar e resumir</button></form></div></div>
@php $tabClass = fn($value) => 'button '.($tipo===$value ? 'button-primary' : 'button-secondary'); @endphp
<div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
<a class="{{ $tabClass(null) }}" href="{{ route('anatel.exclusions',$lista) }}">Todos ({{ number_format($counts['exact']+$counts['regex'],0,',','.') }})</a>
<a class="{{ $tabClass('exact') }}" href="{{ route('anatel.exclusions',[$lista,'tipo'=>'exact']) }}">Exato ({{ number_format($counts['exact'],0,',','.') }})</a>
<a class="{{ $tabClass('regex') }}" href="{{ route('anatel.exclusions',[$lista,'tipo'=>'regex']) }}">Regex ({{ number_format($counts['regex'],0,',','.') }})</a>
</div>
<div class="panel"><div class="table-responsive"><table class="data-table"><thead><tr><th>Tipo</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead><tbody>@forelse($exclusions as $item)<tr><td>{{ $item->type==='exact'?'Exato':'Regex' }}</td><td class="table-mono">{{ $item->value }}</td><td>{{ $item->active?'Ativa':'Inativa' }}</td><td>
<details style="display:inline-block"><summary class="button button-secondary" style="display:inline-block">Editar</summary>
<form method="POST" action="{{ route('anatel.exclusions.update',[$lista,$item]) }}" style="margin-top:8px">@csrf @method('PUT')
<label>Tipo</label><select class="form-control" name="type"><option value="exact" @selected($item->type==='exact')>Domínio exato</option><option value="regex" @selected($item->type==='regex')>Regex explícita</option></select>
<label>Valor</label><input class="form-control" name="value" value="{{ $item->value }}" required>
<label>Observação</label><input class="form-control" name="notes" value="{{ $item->notes }}">
<button class="button button-primary" type="submit">Salvar edição</button>
</form></details>
<form style="display:inline" method="POST" action="{{ route('anatel.exclusions.toggle',[$lista,$item]) }}">@csrf @method('PATCH')<button class="button button-secondary">{{ $item->active?'Desativar':'Reativar' }}</button></form> <form style="display:inline" method="POST" action="{{ route('anatel.exclusions.destroy',[$lista,$item]) }}">@csrf @method('DELETE')<button class="button button-secondary">Excluir</button></form></td></tr>@empty<tr><td colspan="4">Nenhuma exclusão.</td></tr>@endforelse</tbody></table></div>{{ $exclusions->links() }}</div>
@endsection
