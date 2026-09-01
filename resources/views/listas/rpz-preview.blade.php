@extends('layouts.app')
@section('title', 'Preview RPZ — '.$lista->nome)
@section('content')
<div class="page-heading"><div><div class="page-eyebrow">Preview RPZ</div><h1>{{ $lista->nome }}</h1><p>Visualização administrativa do mesmo formato entregue pelo gerador operacional.</p></div><div class="page-actions"><span class="status-pill is-warning">somente leitura</span><a class="button button-secondary" href="{{ route('listas.show', $lista) }}">Voltar</a></div></div>
<div class="rpz-preview-grid">
<section class="panel rpz-preview-summary">
<div class="panel-header"><div><div class="rpz-preview-kicker">Pré-visualização</div><h2>Validação da lista</h2></div><span class="status-pill {{ $stats['invalid'] ? 'is-warning' : 'is-active' }}">{{ $stats['invalid'] ? 'ATENÇÃO' : 'PRONTO' }}</span></div>
<dl class="details-list rpz-preview-metrics">
<div><dt>Lista</dt><dd>{{ $lista->nome }}</dd></div><div><dt>Origem / tipo</dt><dd>{{ strtoupper($lista->origem ?? 'manual') }}</dd></div>
<div><dt>Total cadastrados</dt><dd class="table-mono">{{ number_format($stats['total'],0,',','.') }}</dd></div><div><dt>Ativos</dt><dd class="table-mono">{{ number_format($stats['active'],0,',','.') }}</dd></div>
<div><dt>Inativos</dt><dd class="table-mono">{{ number_format($stats['inactive'],0,',','.') }}</dd></div><div><dt>Excluídos</dt><dd class="table-mono">{{ number_format($stats['excluded'],0,',','.') }}</dd></div>
<div><dt>Duplicidades eliminadas</dt><dd class="table-mono">{{ number_format($stats['duplicates'],0,',','.') }}</dd></div><div><dt>Entradas finais</dt><dd class="table-mono">{{ number_format($stats['entries'],0,',','.') }}</dd></div>
<div><dt>Tamanho estimado</dt><dd class="table-mono">{{ number_format($preview['estimated_bytes'],0,',','.') }} bytes</dd></div><div><dt>Gerado em</dt><dd>{{ $generatedAt->format('d/m/Y H:i:s') }}</dd></div>
</dl>
<div class="rpz-preview-checks"><span>✓ Domínios normalizados</span><span>✓ Exclusões aplicadas</span><span>✓ Duplicidades eliminadas</span>@if($stats['invalid'])<span>⚠ {{ $stats['invalid'] }} registro(s) inválido(s) ignorado(s)</span>@else<span>✓ Sem entradas inválidas</span>@endif<span>✓ Artefato gerado</span></div>
<a class="button button-primary rpz-download-button" href="{{ route('listas.rpz-preview.download',$lista) }}">Baixar RPZ</a>
<form class="rpz-domain-search" method="GET"><label for="rpz-domain">Buscar domínio no artefato</label><div><input id="rpz-domain" name="domain" type="search" maxlength="2048" value="{{ request('domain') }}" placeholder="pizza.com.br"><button class="button button-secondary">Buscar</button></div></form>
@if($search)<div class="rpz-search-result"><strong class="table-mono">{{ $search['domain'] }}</strong>
@if($search['state']==='included')<p>Incluído na RPZ: <b>SIM</b><br>Status: ativo<br>Origem: {{ strtoupper($lista->origem ?? 'manual') }}</p><code>{{ $search['rule'] }}</code>
@elseif($search['state']==='excluded')<p>Incluído na RPZ: <b>NÃO</b><br>Status: excluído<br>Motivo: exclusão administrativa</p>
@elseif($search['state']==='inactive')<p>Incluído na RPZ: <b>NÃO</b><br>Status: inativo</p>
@elseif($search['state']==='invalid')<p>O valor informado não é um domínio válido.</p>@else<p>Domínio não encontrado nesta lista.</p>@endif</div>@endif
</section>
<section class="panel rpz-artifact-panel">
<div class="panel-header"><div><div class="rpz-preview-kicker">Artefato</div><h2>Preview RPZ</h2></div><span class="status-pill is-info">somente leitura</span></div>
<div class="rpz-format-strip"><span>Formato: RPZ</span><span>Action: CNAME .</span><span>TTL: {{ \App\Services\RpzZoneBuilder::TTL }}</span><span>Entradas: {{ number_format($stats['entries'],0,',','.') }}</span></div>
@if($preview['truncated'])<p class="rpz-preview-limit">Exibindo as primeiras {{ number_format($preview['shown'],0,',','.') }} regras de {{ number_format($stats['entries']*2,0,',','.') }}. O download contém o artefato completo.</p>@endif
<pre class="rpz-code"><code>{{ $preview['content'] }}</code></pre>
</section></div>
@endsection
