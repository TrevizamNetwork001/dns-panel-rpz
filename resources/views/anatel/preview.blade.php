@extends('layouts.app')
@section('title','Prévia ANATEL')
@section('content')
<div class="page-heading"><div><div class="page-eyebrow">ANATEL · Prévia</div><h1>{{ $import->original_filename }}</h1><p>Extraído em {{ $import->finished_at?->format('d/m/Y H:i:s') }} · a lista ainda não foi alterada.</p></div><a class="button button-secondary" href="{{ route('anatel.dashboard') }}">Voltar</a></div>
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-card-header"><div class="metric-icon cyan"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div></div>
        <div class="metric-value">{{ number_format($import->valid_count,0,',','.') }}</div>
        <div class="metric-label">Válidos</div>
    </div>
    <div class="metric-card">
        <div class="metric-card-header"><div class="metric-icon green"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/></svg></div></div>
        <div class="metric-value">+{{ number_format($import->new_count,0,',','.') }}</div>
        <div class="metric-label">Novos</div>
    </div>
    <div class="metric-card">
        <div class="metric-card-header"><div class="metric-icon violet"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg></div></div>
        <div class="metric-value">{{ number_format($import->existing_count,0,',','.') }}</div>
        <div class="metric-label">Existentes</div>
    </div>
    <div class="metric-card">
        <div class="metric-card-header"><div class="metric-icon amber"><svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg></div></div>
        <div class="metric-value">{{ number_format($import->excluded_count,0,',','.') }}</div>
        <div class="metric-label">Excluídos</div>
    </div>
</div>
<div class="panel"><div class="panel-header"><h2>Domínios coletados</h2><div><a class="button button-secondary" href="{{ route('anatel.preview.download',$import) }}">Baixar prévia TXT</a>@if($import->status==='awaiting_approval') <form style="display:inline" method="POST" action="{{ route('anatel.approve',$import) }}">@csrf<button class="button button-primary" onclick="return confirm('Aprovar e atualizar a lista ANATEL?')">Aprovar e atualizar lista</button></form> <form style="display:inline" method="POST" action="{{ route('anatel.reject',$import) }}">@csrf<button class="button button-secondary" onclick="return confirm('Não enviar esta prévia ao RPZ?')">Não enviar ao RPZ</button></form>@endif @if(in_array($import->status,['awaiting_approval','rejected','failed','blocked']))<form style="display:inline" method="POST" action="{{ route('anatel.preview.destroy',$import) }}">@csrf @method('DELETE')<button class="button button-secondary" onclick="return confirm('Excluir a prévia e o PDF privado?')">Excluir prévia e PDF</button></form>@endif</div></div>
@php $tabClass = fn($value) => 'button '.($filter===$value ? 'button-primary' : 'button-secondary'); @endphp
<div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <a class="{{ $tabClass(null) }}" href="{{ route('anatel.preview',$import) }}">Todos ({{ number_format($import->valid_count,0,',','.') }})</a>
    <a class="{{ $tabClass('new') }}" href="{{ route('anatel.preview',[$import,'result'=>'new']) }}">Novos ({{ number_format($import->new_count,0,',','.') }})</a>
    <a class="{{ $tabClass('existing') }}" href="{{ route('anatel.preview',[$import,'result'=>'existing']) }}">Existentes ({{ number_format($import->existing_count,0,',','.') }})</a>
    <a class="{{ $tabClass('reactivated') }}" href="{{ route('anatel.preview',[$import,'result'=>'reactivated']) }}">Reativados ({{ number_format($import->reactivated_count,0,',','.') }})</a>
    <a class="{{ $tabClass('excluded') }}" href="{{ route('anatel.preview',[$import,'result'=>'excluded']) }}">Excluídos ({{ number_format($import->excluded_count,0,',','.') }})</a>
</div>
<div class="table-responsive"><table class="data-table"><thead><tr><th>Domínio</th><th>Classificação</th></tr></thead><tbody>@foreach($domains as $domain)<tr><td class="table-mono">{{ $domain->domain }}</td><td>{{ match($domain->result){'new'=>'Novo','existing'=>'Existente','reactivated'=>'Reativar','excluded'=>'Excluído',default=>$domain->result} }}</td></tr>@endforeach</tbody></table></div>{{ $domains->links('pagination.compact') }}</div>
@endsection
