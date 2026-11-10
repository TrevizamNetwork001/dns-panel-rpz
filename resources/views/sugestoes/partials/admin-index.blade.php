@include('listas.partials.admin-detail-styles')
@include('listas.partials.source-tools-styles')
<style>
    .suggestions-admin { max-width:1400px; margin:0 auto; display:grid; gap:18px; }
    .suggestions-admin .page-heading { margin-bottom:0; }
    .suggestions-admin .suggestions-search { display:flex; align-items:flex-end; flex-wrap:wrap; gap:8px; padding-bottom:18px; margin-bottom:16px; border-bottom:1px solid var(--border-soft); }
    .suggestions-admin .suggestions-search .field-group { flex:1; min-width:180px; margin:0; }
    .suggestions-admin .suggestions-search .form-control { font-size:14px; }
    .suggestions-admin .source-filter-tabs { margin:0; }
    .suggestions-admin .suggestions-results { margin-top:18px; }
    .suggestions-admin .suggestions-table { table-layout:fixed; width:100%; }
    .suggestions-admin .suggestions-table th { padding:12px 14px; font-size:11px; }
    .suggestions-admin .suggestions-table td { padding:18px 14px; vertical-align:top; overflow-wrap:anywhere; }
    .suggestions-admin .suggestions-table th:first-child { width:43%; }
    .suggestions-admin .suggestions-table th:nth-child(2) { width:32%; }
    .suggestions-admin .suggestions-table th:nth-child(3) { width:17%; }
    .suggestions-admin .suggestions-table th:last-child { width:8%; }
    .suggestions-admin .suggestion-domain { display:block; font:500 13px/1.8 ui-monospace,SFMono-Regular,Consolas,monospace; color:var(--text); overflow-wrap:anywhere; }
    .suggestions-admin .suggestion-reason { margin:7px 0 0; font-size:12px; line-height:1.8; color:var(--text-soft); white-space:pre-wrap; }
    .suggestions-admin .suggestion-company { display:block; font-size:13px; font-weight:500; color:var(--text); line-height:1.7; }
    .suggestions-admin .suggestion-meta { display:block; margin-top:5px; color:var(--text-muted); font-size:11px; line-height:1.8; }
    .suggestions-admin .suggestion-origin { margin-top:9px; }
    .suggestions-admin .suggestion-origin summary { cursor:pointer; width:fit-content; color:var(--text-muted); font-size:11px; }
    .suggestions-admin .suggestion-origin p { margin:5px 0 0; color:var(--text-soft); font-size:12px; }
    .suggestions-admin .suggestions-actions { display:flex; justify-content:flex-end; }
    .suggestions-admin .status-pill { background:transparent; border:1px solid currentColor; }
    @media(max-width:700px) {
        .suggestions-admin .table-responsive { overflow:visible; }
        .suggestions-admin .suggestions-table thead { display:none; }
        .suggestions-admin .suggestions-table,.suggestions-admin .suggestions-table tbody,.suggestions-admin .suggestions-table tr { display:block; width:100%; }
        .suggestions-admin .suggestions-table tbody { display:grid; gap:12px; }
        .suggestions-admin .suggestions-table tr { padding:14px; border:1px solid var(--border-soft); border-radius:10px; background:var(--surface-soft); }
        .suggestions-admin .suggestions-table td { display:block; width:100%; padding:7px 0; border:0; }
        .suggestions-admin .suggestions-table td[data-label]::before { content:attr(data-label); display:block; font-size:11px; color:var(--text-muted); margin-bottom:5px; }
        .suggestions-admin .suggestions-actions { justify-content:flex-start; }
    }
</style>
<div class="source-tools suggestions-admin">
<div class="page-heading"><div><div class="page-eyebrow"><x-ui-icon name="list" size="14" />REVISÃO DE DOMÍNIOS</div><h1>Sugestões de domínio</h1><p>Analise os domínios enviados pelos clientes e escolha a fonte de destino.</p></div></div>
<section class="panel"><form class="suggestions-search" action="{{ route('sugestoes.index') }}" method="GET"><div class="field-group"><label for="sugestoes-q">Buscar domínio</label><input id="sugestoes-q" class="form-control" type="search" name="q" value="{{ $busca }}" placeholder="exemplo.com"></div>@if($statusFiltro!==null)<input type="hidden" name="status" value="{{ $statusFiltro }}">@endif<button class="button button-secondary" type="submit">Buscar</button>@if($busca!=='')<a class="button button-secondary" href="{{ route('sugestoes.index',array_filter(['status'=>$statusFiltro])) }}">Limpar</a>@endif</form>
<nav class="source-filter-tabs" aria-label="Filtrar sugestões por status">@foreach([''=>'Todas','pending'=>'Pendentes','approved'=>'Aprovadas','rejected'=>'Rejeitadas'] as $value=>$label)@php $selected=$statusFiltro===($value===''?null:$value); @endphp<a class="source-filter-tab {{ $selected?'is-selected':'' }}" @if($selected) aria-current="page" @endif href="{{ route('sugestoes.index',array_filter(['status'=>$value,'q'=>$busca])) }}">{{ $label }}</a>@endforeach</nav></section>
<section class="panel suggestions-results"><div class="panel-header"><h2>Sugestões recebidas <span class="source-small-count">{{ number_format($sugestoes->total(),0,',','.') }}</span></h2><span class="source-tool-caption">Mais recentes primeiro</span></div>
@if($sugestoes->isEmpty())
<div class="empty-state"><x-ui-icon name="check" size="26" /><div>@if($statusFiltro==='pending'&&$busca==='')<strong>Nenhuma sugestão aguardando análise.</strong>@elseif($statusFiltro!==null||$busca!=='')<strong>Nenhuma sugestão corresponde aos filtros selecionados.</strong>@else<strong>Nenhuma sugestão ainda</strong><span>As sugestões enviadas pelos clientes aparecerão aqui.</span>@endif</div></div>
@else
<div class="table-responsive"><table class="data-table suggestions-table"><thead><tr><th>Domínio e motivo</th><th>Origem da sugestão</th><th>Status</th><th><span class="source-tool-caption">Ações</span></th></tr></thead><tbody>
@foreach($sugestoes as $sugestao)
<tr><td><strong class="suggestion-domain">{{ $sugestao->dominio }}</strong>@if($sugestao->motivo)<p class="suggestion-reason">{{ $sugestao->motivo }}</p>@endif</td><td data-label="Origem"><strong class="suggestion-company">{{ $sugestao->empresa->nome }}</strong><span class="suggestion-meta">{{ $sugestao->criadoPor->name??'—' }}</span><time class="suggestion-meta" datetime="{{ $sugestao->created_at->toIso8601String() }}">{{ $sugestao->created_at->format('d/m/Y H:i') }}</time>@if($sugestao->ip_address)<details class="suggestion-origin"><summary>IP de origem</summary><p>{{ $sugestao->ip_address }}</p></details>@endif</td>
<td data-label="Status">@if($sugestao->status==='pending')<span class="status-pill is-warning">Pendente</span>@elseif($sugestao->status==='approved')<span class="status-pill is-active">Aprovada</span><span class="suggestion-meta">{{ $sugestao->lista->nome??'—' }}</span>@else<span class="status-pill is-inactive">Rejeitada</span>@endif</td>
<td><div class="suggestions-actions">@if($sugestao->status==='pending')<x-actions-menu label="Ações da sugestão {{ $sugestao->dominio }}">@foreach(['aprovar'=>'Aprovar','rejeitar'=>'Rejeitar'] as $action=>$label)<button type="button" class="actions-menu-item {{ $action==='rejeitar'?'actions-menu-item-danger':'' }}" data-suggestion-action="{{ $action }}" data-url="{{ route('sugestoes.'.$action,$sugestao) }}" aria-haspopup="dialog" aria-controls="suggestion-{{ $action }}">{{ $label }}</button>@endforeach<template><dl class="suggestion-context"><div><dt>Domínio</dt><dd data-domain>{{ $sugestao->dominio }}</dd></div><div><dt>Empresa</dt><dd>{{ $sugestao->empresa->nome }}</dd></div><div><dt>Enviado por</dt><dd>{{ $sugestao->criadoPor->name??'—' }}</dd></div><div><dt>Motivo</dt><dd>{{ $sugestao->motivo?:'—' }}</dd></div></dl></template></x-actions-menu>@endif</div></td></tr>
@endforeach
</tbody></table></div>
{{ $sugestoes->links('pagination.compact') }}
@endif
</section>
</div>
@if($sugestoes->contains('status','pending'))
@include('sugestoes.partials.review-dialogs')
@endif
