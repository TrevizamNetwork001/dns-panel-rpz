@extends('layouts.app')
@section('title','Lote ANATEL')
@section('content')
@include('anatel.partials.styles')
@include('anatel.partials.navigation')
@include('listas.partials.admin-detail-styles')
@include('listas.partials.source-tools-styles')
<div class="source-tools anatel-review">
<nav class="source-breadcrumb" aria-label="Navegação"><a href="{{ route('anatel.dashboard',['lista'=>$lista->id]) }}">Central ANATEL</a><x-ui-icon name="chevron" size="13" /><span>Revisar lote</span></nav>
<div class="page-heading"><div><div class="page-eyebrow">ANATEL / PUBLICAÇÃO</div><h1>Revisão do lote</h1><p>{{ $lista->nome }} · {{ $pending->count() }} arquivo(s) aguardando aprovação.</p></div><a class="button button-secondary" href="{{ route('anatel.dashboard',['lista'=>$lista->id]) }}">Voltar à central</a></div>
<section class="panel"><div class="panel-header"><h2>Arquivos do lote <span class="source-small-count">{{ $pending->count() }}</span></h2></div>
<ul class="source-import-list">
@forelse($pending as $import)
<li class="source-import-card"><div class="source-import-top"><span class="source-import-icon"><x-ui-icon name="list" size="18" /></span><div class="source-import-title"><strong>{{ $import->original_filename }}</strong><time>Importação #{{ $import->id }}@if($import->finished_at) · {{ $import->finished_at->format('d/m/Y H:i') }}@endif</time></div><a class="button button-secondary" href="{{ route('anatel.preview',$import) }}">Revisar arquivo</a></div><div class="anatel-import-bottom"><div class="anatel-import-numbers"><span><strong>{{ number_format($import->valid_count,0,',','.') }}</strong> válidos</span><span><strong>+{{ number_format($import->new_count,0,',','.') }}</strong> novos</span></div></div></li>
@empty
<li class="anatel-clear"><span class="anatel-clear-icon"><x-ui-icon name="check" size="21" /></span><div><h3>Tudo em dia</h3><p>Nenhum arquivo pendente de aprovação.</p></div></li>
@endforelse
</ul>
@if($pending->isNotEmpty())<div class="anatel-review-decision"><p>Nenhuma alteração deste lote foi publicada no RPZ. Confira os arquivos antes de publicar.</p><div class="anatel-review-actions"><form method="POST" action="{{ route('anatel.batch.publish',$lista) }}">@csrf<button class="button button-primary" onclick="return confirm('Publicar todo o lote no RPZ?')">Publicar lote no RPZ</button></form><form method="POST" action="{{ route('anatel.batch.reject',$lista) }}">@csrf<button class="button button-secondary" onclick="return confirm('Rejeitar todo o lote?')">Não enviar lote ao RPZ</button></form></div></div>@endif
</section>
<section class="panel"><div class="panel-header"><h2>Domínios únicos consolidados</h2><span class="source-tool-caption">{{ number_format($counts->sum(),0,',','.') }} domínios no lote</span></div>
@include('anatel.partials.review-domains',['reviewCounts'=>$counts,'reviewRoute'=>'anatel.batch','reviewTarget'=>$lista,'showOccurrences'=>true])
</section>
</div>
@endsection
