@extends('layouts.app')
@section('title', 'Exceções · '.$empresa->nome)
@section('content')
@include('listas.partials.admin-detail-styles')
@include('listas.partials.source-tools-styles')
<style>
    .company-exception-summary { display:flex; align-items:flex-start; gap:12px; padding:14px; margin-bottom:18px; border:1px solid var(--border-soft); border-radius:9px; background:var(--surface-soft); }
    .company-exception-summary svg { flex-shrink:0; color:var(--cyan); margin-top:2px; }
    .company-exception-summary p { margin:0; font-size:12px; line-height:1.8; color:var(--text-soft); }
    .company-exception-reason { margin:12px 0 0; font-size:12px; line-height:1.8; color:var(--text-soft); overflow-wrap:anywhere; }
    .company-exception-reason span { display:block; font-size:11px; color:var(--text-muted); margin-bottom:3px; }
    .company-exception-empty { display:grid; justify-items:start; gap:10px; padding:20px 0; }
    .company-exception-empty > svg { color:var(--cyan); }
    .company-exception-empty h3 { font-size:14px; color:var(--text); font-weight:500; margin:0; }
    .company-exception-empty p { font-size:12px; color:var(--text-muted); line-height:1.8; margin:0; }
    .company-exceptions .source-exclusion-form .source-help { line-height:1.8; }
</style>
<div class="source-tools company-exceptions">
<nav class="source-breadcrumb" aria-label="Navegação"><a href="{{ route('empresas.index') }}">Empresas</a><x-ui-icon name="chevron" size="13" /><a href="{{ route('empresas.show',$empresa) }}">{{ $empresa->nome }}</a><x-ui-icon name="chevron" size="13" /><span>Exceções</span></nav>
<div class="page-heading"><div><div class="page-eyebrow"><x-ui-icon name="shield" size="14" />POLÍTICA DE BLOQUEIO</div><h1>Exceções da empresa</h1><p>{{ $empresa->nome }} · gerencie os domínios liberados.</p></div><div class="page-actions"><a href="{{ route('empresas.show',$empresa) }}" class="button button-secondary">Voltar à empresa</a></div></div>
<div class="source-exclusions-grid">
<section class="panel"><div class="panel-header"><h2>Liberar domínio</h2></div>
<div class="company-exception-summary"><x-ui-icon name="shield" size="18" /><p>A liberação inclui o domínio e seus subdomínios, somente nesta empresa. As fontes compartilhadas mantêm seus bloqueios.</p></div>
<form method="POST" action="{{ route('empresas.exceptions.store',$empresa) }}" class="source-exclusion-form">@csrf
<div class="field-group"><label for="dominio">Domínio</label><input id="dominio" name="dominio" class="form-control" value="{{ old('dominio') }}" placeholder="servico.exemplo.com.br" maxlength="253" required autocapitalize="none" spellcheck="false" aria-describedby="exception-domain-help" @error('dominio') aria-invalid="true" @enderror><p id="exception-domain-help" class="source-help">Informar www.exemplo.com.br libera somente esse ramo, preservando o bloqueio de exemplo.com.br.</p>@error('dominio')<span class="source-field-error">{{ $message }}</span>@enderror</div>
<div class="field-group"><label for="motivo">Motivo <span class="source-tool-caption">(opcional)</span></label><input id="motivo" name="motivo" class="form-control" value="{{ old('motivo') }}" maxlength="255" placeholder="Ex.: serviço necessário à operação">@error('motivo')<span class="source-field-error">{{ $message }}</span>@enderror</div>
<div class="source-actions"><button class="button button-primary" type="submit">Adicionar exceção</button></div>
</form></section>
<section class="panel"><div class="panel-header"><h2>Domínios liberados <span class="source-small-count">{{ number_format($exceptions->total(),0,',','.') }}</span></h2><span class="source-tool-caption">Domínio e subdomínios</span></div>
@if($exceptions->isEmpty())
<div class="company-exception-empty"><x-ui-icon name="shield" size="26" /><h3>Nenhuma exceção cadastrada</h3><p>Adicione um domínio para liberar seu acesso nesta empresa.</p></div>
@else
<ul class="source-exclusion-list">
@foreach($exceptions as $exception)
<li class="source-exclusion-card"><div class="source-exclusion-top"><span class="status-pill is-active">Liberado</span></div><strong class="source-exclusion-value">{{ $exception->dominio }}</strong>
@if($exception->motivo)<p class="company-exception-reason"><span>Motivo</span>{{ $exception->motivo }}</p>@endif
<div class="source-exclusion-controls"><form method="POST" action="{{ route('empresas.exceptions.destroy',[$empresa,$exception]) }}">@csrf @method('DELETE')<button type="submit" class="source-text-action is-danger" aria-label="Remover exceção de {{ $exception->dominio }}" onclick="return confirm('Remover esta exceção e voltar a aplicar os bloqueios das fontes?')">Remover exceção</button></form></div>
</li>
@endforeach
</ul>
{{ $exceptions->links('pagination.compact') }}
@endif
</section>
</div>
</div>
@endsection
