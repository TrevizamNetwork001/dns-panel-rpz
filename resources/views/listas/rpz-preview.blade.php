@extends('layouts.app')
@section('title', 'Preview RPZ — '.$lista->nome)
@section('content')
@include('listas.partials.admin-detail-styles')
@include('listas.partials.source-tools-styles')
<div class="source-tools source-preview">
    <nav class="source-breadcrumb" aria-label="Navegação"><a href="{{ route('listas.index') }}">Fontes</a><x-ui-icon name="chevron" size="13" /><a href="{{ route('listas.show',$lista) }}">{{ $lista->nome }}</a><x-ui-icon name="chevron" size="13" /><span>Preview RPZ</span></nav>
    <div class="page-heading"><div><div class="page-eyebrow"><x-ui-icon name="list" size="14" />Inspeção da zona</div><h1>Preview RPZ</h1><p>{{ $lista->nome }} · consulte o conteúdo gerado sem alterar a fonte.</p></div><div class="page-actions"><a class="button button-secondary" href="{{ route('listas.show',$lista) }}">Voltar à fonte</a><a class="button button-primary" href="{{ route('listas.rpz-preview.download',$lista) }}">Baixar RPZ</a></div></div>
    <div class="source-preview-grid">
        <section class="panel source-preview-summary">
            <div class="panel-header"><h2>Resumo da geração</h2><span class="status-pill {{ $stats['invalid'] ? 'is-warning' : 'is-active' }}">{{ $stats['invalid'] ? 'ATENÇÃO' : 'PRONTO' }}</span></div>
            <div class="source-preview-total"><strong>{{ number_format($stats['entries'],0,',','.') }}</strong><span>Entradas finais</span></div>
            <dl class="details-list">
                <div><dt>Total cadastrados</dt><dd>{{ number_format($stats['total'],0,',','.') }}</dd></div>
                <div><dt>Ativos</dt><dd>{{ number_format($stats['active'],0,',','.') }}</dd></div>
                <div><dt>Tamanho estimado</dt><dd>{{ number_format($preview['estimated_bytes'] / 1000000,2,',','.') }} MB</dd></div>
                <div><dt>Origem / tipo</dt><dd>{{ strtoupper($lista->origem ?? 'manual') }}</dd></div>
            </dl>
            <details class="source-tool-details"><summary>Detalhes do processamento</summary><dl class="details-list">
                <div><dt>Inativos</dt><dd>{{ number_format($stats['inactive'],0,',','.') }}</dd></div><div><dt>Excluídos</dt><dd>{{ number_format($stats['excluded'],0,',','.') }}</dd></div>
                <div><dt>Duplicidades eliminadas</dt><dd>{{ number_format($stats['duplicates'],0,',','.') }}</dd></div><div><dt>Gerado em</dt><dd>{{ $generatedAt->format('d/m/Y H:i:s') }}</dd></div>
            </dl></details>
            <ul class="source-validation"><li><x-ui-icon name="check" size="14" />Domínios normalizados</li><li><x-ui-icon name="check" size="14" />Exclusões aplicadas</li><li><x-ui-icon name="check" size="14" />Duplicidades eliminadas</li><li><x-ui-icon name="{{ $stats['invalid'] ? 'alert' : 'check' }}" size="14" />{{ $stats['invalid'] ? $stats['invalid'].' registro(s) inválido(s) ignorado(s)' : 'Sem entradas inválidas' }}</li></ul>
        </section>
        <div class="source-preview-content">
            <section class="panel source-domain-search">
                <div class="panel-header"><div><h2>Consultar domínio</h2><p>Veja se um domínio está incluído nesta RPZ.</p></div><x-ui-icon name="search" size="18" /></div>
                <form method="GET" action="{{ route('listas.rpz-preview',$lista) }}"><label for="rpz-domain">Buscar domínio no artefato</label><div><input class="form-control" id="rpz-domain" name="domain" type="search" maxlength="2048" value="{{ request('domain') }}" placeholder="exemplo.com"><button class="button button-secondary" type="submit">Buscar</button>@if(request()->filled('domain'))<a class="button button-secondary" href="{{ route('listas.rpz-preview',$lista) }}">Limpar</a>@endif</div></form>
                @if($search)
                <div class="source-search-result" role="status"><strong>{{ $search['domain'] }}</strong>
                    @if($search['state']==='included')<p>Incluído na RPZ: <b>SIM</b> · Status: ativo · Origem: {{ strtoupper($lista->origem ?? 'manual') }}</p><code>{{ $search['rule'] }}</code>
                    @elseif($search['state']==='excluded')<p>Incluído na RPZ: <b>NÃO</b> · Status: excluído · Motivo: exclusão administrativa</p>
                    @elseif($search['state']==='inactive')<p>Incluído na RPZ: <b>NÃO</b> · Status: inativo</p>
                    @elseif($search['state']==='invalid')<p>O valor informado não é um domínio válido.</p>@else<p>Domínio não encontrado nesta lista.</p>@endif
                </div>
                @endif
            </section>
            <section class="panel source-artifact">
                <div class="panel-header"><h2>Conteúdo da zona</h2><span class="source-readonly-badge">Somente leitura</span></div>
                <div class="source-format"><span>RPZ</span><span>CNAME .</span><span>TTL: {{ \App\Services\RpzZoneBuilder::TTL }}</span></div>
                @if($preview['truncated'])<p class="source-help">Exibindo as primeiras {{ number_format($preview['shown'],0,',','.') }} regras de {{ number_format($stats['entries']*2,0,',','.') }}. O download contém o artefato completo.</p>@endif
                <pre class="rpz-code" tabindex="0" aria-label="Conteúdo da zona RPZ"><code>{{ $preview['content'] }}</code></pre>
            </section>
        </div>
    </div>
</div>
@endsection
