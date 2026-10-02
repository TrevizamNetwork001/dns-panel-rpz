@extends('layouts.app')
@section('title','Lote ANATEL')
@section('content')
<div class="page-heading"><div><div class="page-eyebrow">ANATEL · Auditoria mestre</div><h1>Lote pendente — {{ $lista->nome }}</h1><p>{{ $pending->count() }} PDF(s). Nenhuma alteração foi publicada no RPZ.</p></div><a class="button button-secondary" href="{{ route('anatel.dashboard') }}">Voltar</a></div>
<div class="panel"><div class="panel-header"><h2>PDFs do lote</h2><div>@if($pending->isNotEmpty())<form style="display:inline" method="POST" action="{{ route('anatel.batch.publish',$lista) }}">@csrf<button class="button button-primary" onclick="return confirm('Publicar todo o lote no RPZ?')">Publicar lote no RPZ</button></form> <form style="display:inline" method="POST" action="{{ route('anatel.batch.reject',$lista) }}">@csrf<button class="button button-secondary" onclick="return confirm('Rejeitar todo o lote?')">Não enviar lote ao RPZ</button></form>@endif</div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>ID</th><th>Arquivo</th><th>Data/hora</th><th>Válidos</th><th>Novos</th><th></th></tr></thead><tbody>@forelse($pending as $import)<tr><td>#{{ $import->id }}</td><td>{{ $import->original_filename }}</td><td>{{ $import->finished_at?->format('d/m/Y H:i:s') }}</td><td>{{ $import->valid_count }}</td><td>+{{ $import->new_count }}</td><td><a href="{{ route('anatel.preview',$import) }}">Revisar PDF</a></td></tr>@empty<tr><td colspan="6">Nenhum PDF pendente.</td></tr>@endforelse</tbody></table></div></div>
<div class="panel">
    <div class="panel-header"><h2>Domínios únicos consolidados</h2></div>
    @php
        $total = $counts->sum();
        $tabClass = fn($value) => 'button '.($filter===$value ? 'button-primary' : 'button-secondary');
    @endphp
    <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <a class="{{ $tabClass(null) }}" href="{{ route('anatel.batch',$lista) }}">Todos ({{ number_format($total,0,',','.') }})</a>
        <a class="{{ $tabClass('new') }}" href="{{ route('anatel.batch',[$lista,'result'=>'new']) }}">Novos ({{ number_format($counts['new'] ?? 0,0,',','.') }})</a>
        <a class="{{ $tabClass('existing') }}" href="{{ route('anatel.batch',[$lista,'result'=>'existing']) }}">Existentes ({{ number_format($counts['existing'] ?? 0,0,',','.') }})</a>
        <a class="{{ $tabClass('reactivated') }}" href="{{ route('anatel.batch',[$lista,'result'=>'reactivated']) }}">Reativados ({{ number_format($counts['reactivated'] ?? 0,0,',','.') }})</a>
        <a class="{{ $tabClass('excluded') }}" href="{{ route('anatel.batch',[$lista,'result'=>'excluded']) }}">Excluídos ({{ number_format($counts['excluded'] ?? 0,0,',','.') }})</a>
    </div>
    <div class="table-responsive"><table class="data-table"><thead><tr><th>Domínio</th><th>Classificação</th><th>Ocorrências nos PDFs</th></tr></thead><tbody>@forelse($domains as $domain)<tr><td class="table-mono">{{ $domain->domain }}</td><td>{{ match($domain->result){'new'=>'Novo','existing'=>'Existente','reactivated'=>'Reativar','excluded'=>'Excluído',default=>$domain->result} }}</td><td>{{ $domain->occurrences }}</td></tr>@empty<tr><td colspan="3">Nenhum domínio nessa categoria.</td></tr>@endforelse</tbody></table></div>{{ $domains->links('pagination.compact') }}
</div>
@endsection
