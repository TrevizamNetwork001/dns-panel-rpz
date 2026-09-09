@extends('layouts.app')
@section('title', 'Grupos RBL')
@section('content')
<div class="page-heading"><h1>Grupos RBL</h1><a class="button button-primary" href="{{ route('rbl.groups.create') }}">Novo grupo</a></div>
@include('rbl.navigation')
<div class="panel"><table class="data-table"><thead><tr><th>Nome</th><th>Categoria</th><th>Status</th><th>Alvos</th></tr></thead><tbody>@forelse($groups as $group)<tr><td><a href="{{ route('rbl.groups.show',$group) }}">{{ $group->name }}</a></td><td>{{ $group->category ?? '—' }}</td><td>{{ $group->enabled ? 'Ativo' : 'Desativado' }}</td><td>{{ $group->targets_count }}</td></tr>@empty<tr><td colspan="4">Nenhum grupo cadastrado.</td></tr>@endforelse</tbody></table>{{ $groups->links() }}</div>
@endsection
