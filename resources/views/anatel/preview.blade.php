@extends('layouts.app')
@section('title','Prévia ANATEL')
@section('content')
@include('anatel.partials.styles')
@include('anatel.partials.navigation')
@include('listas.partials.admin-detail-styles')
@include('listas.partials.source-tools-styles')
@php
    $reviewCounts=collect(['new'=>$import->new_count,'existing'=>$import->existing_count,'reactivated'=>$import->reactivated_count,'excluded'=>$import->excluded_count]);
    $statusClass=match($import->status){'completed'=>'is-active','awaiting_approval','blocked'=>'is-warning','failed','rejected'=>'is-inactive',default=>'is-info'};
@endphp
<div class="source-tools anatel-review">
<nav class="source-breadcrumb" aria-label="Navegação"><a href="{{ route('anatel.dashboard',['lista'=>$import->lista_id]) }}">Central ANATEL</a><x-ui-icon name="chevron" size="13" /><span>Revisar arquivo</span></nav>
<div class="page-heading"><div><div class="page-eyebrow">ANATEL / REVISÃO</div><h1>Prévia da importação</h1><p>{{ $import->lista->nome }}</p></div><div class="page-actions"><a class="button button-secondary" href="{{ route('anatel.dashboard',['lista'=>$import->lista_id]) }}">Voltar à central</a><a class="button button-secondary" href="{{ route('anatel.preview.download',$import) }}">Baixar prévia TXT</a></div></div>
<section class="panel"><div class="source-import-top"><span class="source-import-icon"><x-ui-icon name="list" size="18" /></span><div class="source-import-title"><strong>{{ $import->original_filename }}</strong><time @if($import->finished_at) datetime="{{ $import->finished_at->toIso8601String() }}" @endif>Importação #{{ $import->id }}@if($import->finished_at) · {{ $import->finished_at->format('d/m/Y H:i') }}@endif</time></div><span class="status-pill {{ $statusClass }}">{{ $import->status_label }}</span></div>
<dl class="anatel-review-counts">@foreach(['Válidos'=>$import->valid_count,'Novos'=>$import->new_count,'Existentes'=>$import->existing_count,'Reativados'=>$import->reactivated_count,'Excluídos'=>$import->excluded_count,'Inválidos'=>$import->invalid_count] as $label=>$count)<div><dt>{{ $label }}</dt><dd>{{ number_format($count,0,',','.') }}</dd></div>@endforeach</dl>
@if($import->status==='awaiting_approval')
<div class="anatel-review-decision"><p>Confira os domínios antes de aprovar. Esta prévia ainda não alterou a fonte.</p><div class="anatel-review-actions"><form method="POST" action="{{ route('anatel.approve',$import) }}">@csrf<button class="button button-primary" onclick="return confirm('Aprovar e atualizar a lista ANATEL?')">Aprovar e atualizar lista</button></form><form method="POST" action="{{ route('anatel.reject',$import) }}">@csrf<button class="button button-secondary" onclick="return confirm('Não enviar esta prévia ao RPZ?')">Não enviar ao RPZ</button></form></div></div>
@endif
@if(in_array($import->status,['awaiting_approval','rejected','failed','blocked']))<details class="anatel-review-options"><summary>Mais opções</summary><form method="POST" action="{{ route('anatel.preview.destroy',$import) }}">@csrf @method('DELETE')<button class="source-text-action is-danger" onclick="return confirm('Excluir a prévia e o PDF privado?')">Excluir prévia e PDF</button></form></details>@endif
</section>
<section class="panel"><div class="panel-header"><h2>Domínios coletados</h2><span class="source-tool-caption">{{ number_format($domains->total(),0,',','.') }} nesta seleção</span></div>
@include('anatel.partials.review-domains',['reviewRoute'=>'anatel.preview','reviewTarget'=>$import,'showOccurrences'=>false])
</section>
</div>
@endsection
