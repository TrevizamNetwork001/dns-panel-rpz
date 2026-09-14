@extends('layouts.app')
@section('title','Histórico ANATEL')
@section('content')
<div class="page-heading"><div><div class="page-eyebrow">ANATEL</div><h1>Histórico de importações</h1><p>{{ $lista->nome }}</p></div><a class="button button-secondary" href="{{ route('listas.show',$lista) }}">Voltar</a></div>
<div class="panel"><div class="table-responsive"><table class="data-table"><thead><tr><th>Data</th><th>Arquivo</th><th>Resultado</th><th>Novos</th><th>Existentes</th><th>Excluídos</th><th>Inválidos</th><th></th></tr></thead><tbody>
@forelse($imports as $import)<tr><td>{{ $import->created_at->format('d/m/Y H:i') }}</td><td>{{ $import->original_filename }}</td><td><span class="status-pill {{ $import->status==='completed'?'is-active':($import->status==='blocked'?'is-warning':'is-inactive') }}">{{ $import->status_label }}</span></td><td>+{{ $import->new_count }}</td><td>{{ $import->existing_count }}</td><td>{{ $import->excluded_count }}</td><td>{{ $import->invalid_count }}</td><td>@if($import->status==='completed')<a href="{{ route('anatel.imports.new',[$lista,$import]) }}">Baixar novos</a>@endif</td></tr>@empty<tr><td colspan="8">Nenhuma importação.</td></tr>@endforelse
</tbody></table></div>{{ $imports->links() }}</div>
@endsection
