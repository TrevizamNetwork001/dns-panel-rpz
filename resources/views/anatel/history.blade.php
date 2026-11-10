@extends('layouts.app')
@section('title','Histórico ANATEL')
@section('content')
@include('listas.partials.admin-detail-styles')
@include('listas.partials.source-tools-styles')
<div class="source-tools">
    <nav class="source-breadcrumb" aria-label="Navegação"><a href="{{ route('listas.index') }}">Fontes</a><x-ui-icon name="chevron" size="13" /><a href="{{ route('listas.show',$lista) }}">{{ $lista->nome }}</a><x-ui-icon name="chevron" size="13" /><span>Importações</span></nav>
    <div class="page-heading"><div><div class="page-eyebrow"><x-ui-icon name="clock" size="14" />ANATEL / PDF</div><h1>Histórico de importações</h1><p>{{ $lista->nome }} · acompanhe o processamento de cada arquivo.</p></div><div class="page-actions"><a class="button button-secondary" href="{{ route('listas.historico',$lista) }}">Alterações de domínios</a><a class="button button-primary" href="{{ route('anatel.dashboard',['lista'=>$lista->id]) }}">Importar arquivos</a></div></div>
    <section class="panel source-import-history">
        <div class="panel-header"><h2>Arquivos recebidos <span class="source-small-count">{{ number_format($imports->total(),0,',','.') }}</span></h2><span class="source-tool-caption">Mais recentes primeiro</span></div>
        @if($imports->isEmpty())
            <div class="empty-state"><x-ui-icon name="list" size="28" /><div><strong>Nenhuma importação.</strong><span>Os arquivos enviados e seus resultados aparecerão aqui.</span></div></div>
        @else
            <ul class="source-import-list">
            @foreach($imports as $import)
                @php $statusClass=match($import->status){'completed'=>'is-active','awaiting_approval','blocked'=>'is-warning','failed','rejected'=>'is-inactive',default=>'is-info'}; @endphp
                <li class="source-import-card">
                    <div class="source-import-top"><span class="source-import-icon"><x-ui-icon name="list" size="18" /></span><div class="source-import-title"><strong>{{ $import->original_filename }}</strong><time datetime="{{ $import->created_at->toIso8601String() }}">{{ $import->created_at->format('d/m/Y H:i') }}@if($import->user) · {{ $import->user->name }}@endif</time></div><span class="status-pill {{ $statusClass }}">{{ $import->status_label }}</span></div>
                    <dl class="source-import-counts"><div><dt>Novos</dt><dd>+{{ number_format($import->new_count,0,',','.') }}</dd></div><div><dt>Existentes</dt><dd>{{ number_format($import->existing_count,0,',','.') }}</dd></div><div><dt>Excluídos</dt><dd>{{ number_format($import->excluded_count,0,',','.') }}</dd></div><div><dt>Inválidos</dt><dd>{{ number_format($import->invalid_count,0,',','.') }}</dd></div></dl>
                    @if($import->status==='awaiting_approval')<div class="source-import-actions"><a class="button button-secondary" href="{{ route('anatel.preview',$import) }}">Revisar prévia</a></div>@elseif($import->status==='completed')<div class="source-import-actions"><a class="button button-secondary" href="{{ route('anatel.imports.new',[$lista,$import]) }}">Baixar novos</a></div>@endif
                </li>
            @endforeach
            </ul>
            {{ $imports->links('pagination.compact') }}
        @endif
    </section>
</div>
@endsection
