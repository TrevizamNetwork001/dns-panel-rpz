@extends('layouts.app')
@section('title','Exclusões ANATEL')
@section('content')
@include('anatel.partials.styles')
@include('anatel.partials.navigation')
@include('listas.partials.admin-detail-styles')
@include('listas.partials.source-tools-styles')
<div class="source-tools">
    <nav class="source-breadcrumb" aria-label="Navegação"><a href="{{ route('listas.index') }}">Fontes</a><x-ui-icon name="chevron" size="13" /><a href="{{ route('listas.show',$lista) }}">{{ $lista->nome }}</a><x-ui-icon name="chevron" size="13" /><span>Exclusões</span></nav>
    <div class="page-heading"><div><div class="page-eyebrow"><x-ui-icon name="shield" size="14" />ANATEL / PDF</div><h1>Exclusões</h1><p>{{ $lista->nome }} · gerencie as regras de liberação desta fonte.</p></div><a class="button button-secondary" href="{{ route('listas.show',$lista) }}">Voltar à fonte</a></div>
    <div class="source-exclusions-grid">
        <div class="source-exclusion-sidebar">
            <section class="panel"><div class="panel-header"><h2>Nova exclusão</h2></div>
                <form method="POST" action="{{ route('anatel.exclusions.store',$lista) }}" class="source-exclusion-form">
                    @csrf
                    <div class="field-group"><label for="exclusion-type">Tipo</label><select class="form-control source-short-select" id="exclusion-type" name="type"><option value="exact" @selected(old('type','exact')==='exact')>Domínio exato</option><option value="regex" @selected(old('type')==='regex')>Regex explícita</option></select></div>
                    <div class="field-group"><label for="exclusion-value">Valor</label><input class="form-control" id="exclusion-value" name="value" value="{{ old('value') }}" maxlength="1000" required placeholder="exemplo.com">@error('value')<span class="source-field-error">{{ $message }}</span>@enderror</div>
                    <div class="field-group"><label for="exclusion-notes">Observação <span class="source-tool-caption">(opcional)</span></label><input class="form-control" id="exclusion-notes" name="notes" value="{{ old('notes') }}" maxlength="1000"></div>
                    <p class="source-help">Domínio exato nunca é interpretado como regex.</p>
                    <div class="source-actions"><button class="button button-primary" type="submit">Salvar</button></div>
                </form>
            </section>
            <details class="panel source-legacy-import"><summary>Importar exclude_list antigo</summary><p class="source-help">Envie o arquivo de exclusões para importar e conferir o resumo.</p><form method="POST" enctype="multipart/form-data" action="{{ route('anatel.exclusions.legacy',$lista) }}" class="source-exclusion-form">@csrf<div class="field-group"><label for="exclude-list-file">Arquivo de exclusões</label><input class="form-control" type="file" id="exclude-list-file" name="exclude_list" required></div><div class="source-actions"><button class="button button-secondary" type="submit">Importar e resumir</button></div></form></details>
        </div>
        <section class="panel source-exclusion-results">
            <div class="panel-header"><h2>Regras de exclusão</h2><span class="source-tool-caption">{{ number_format($exclusions->total(),0,',','.') }} nesta seleção</span></div>
            @php $tabClass=fn($value)=>'source-filter-tab'.($tipo===$value?' is-selected':''); @endphp
            <nav class="source-filter-tabs" aria-label="Filtrar exclusões por tipo">
                <a class="{{ $tabClass(null) }}" @if($tipo===null) aria-current="page" @endif href="{{ route('anatel.exclusions',$lista) }}">Todos ({{ number_format($counts['exact']+$counts['regex'],0,',','.') }})</a>
                <a class="{{ $tabClass('exact') }}" @if($tipo==='exact') aria-current="page" @endif href="{{ route('anatel.exclusions',[$lista,'tipo'=>'exact']) }}">Exato ({{ number_format($counts['exact'],0,',','.') }})</a>
                <a class="{{ $tabClass('regex') }}" @if($tipo==='regex') aria-current="page" @endif href="{{ route('anatel.exclusions',[$lista,'tipo'=>'regex']) }}">Regex ({{ number_format($counts['regex'],0,',','.') }})</a>
            </nav>
            @if($exclusions->isEmpty())
                <div class="empty-state"><x-ui-icon name="shield" size="26" /><div><strong>Nenhuma exclusão.</strong><span>As regras cadastradas aparecerão aqui.</span></div></div>
            @else
                <ul class="source-exclusion-list">
                @foreach($exclusions as $item)
                    <li class="source-exclusion-card">
                        <div class="source-exclusion-top"><span class="source-exclusion-type">{{ $item->type==='exact'?'Exato':'Regex' }}</span><span class="status-pill {{ $item->active?'is-active':'is-muted' }}">{{ $item->active?'Ativa':'Inativa' }}</span></div>
                        <strong class="source-exclusion-value">{{ $item->value }}</strong>
                        @if($item->notes)<p class="source-exclusion-notes">{{ $item->notes }}</p>@endif
                        <div class="source-exclusion-controls">
                            <details class="source-exclusion-edit"><summary>Editar</summary>
                                <form method="POST" action="{{ route('anatel.exclusions.update',[$lista,$item]) }}" class="source-exclusion-form">@csrf @method('PUT')
                                    <div class="field-group"><label for="exclusion-type-{{ $item->id }}">Tipo</label><select class="form-control source-short-select" id="exclusion-type-{{ $item->id }}" name="type"><option value="exact" @selected($item->type==='exact')>Domínio exato</option><option value="regex" @selected($item->type==='regex')>Regex explícita</option></select></div>
                                    <div class="field-group"><label for="exclusion-value-{{ $item->id }}">Valor</label><input class="form-control" id="exclusion-value-{{ $item->id }}" name="value" value="{{ $item->value }}" maxlength="1000" required></div>
                                    <div class="field-group"><label for="exclusion-notes-{{ $item->id }}">Observação</label><input class="form-control" id="exclusion-notes-{{ $item->id }}" name="notes" value="{{ $item->notes }}" maxlength="1000"></div>
                                    <div class="source-actions"><button class="button button-primary" type="submit">Salvar edição</button></div>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('anatel.exclusions.toggle',[$lista,$item]) }}">@csrf @method('PATCH')<button class="source-text-action {{ $item->active ? 'is-toggle-off' : 'is-toggle-on' }}" type="submit">{{ $item->active?'Desativar':'Reativar' }}</button></form>
                            <form method="POST" action="{{ route('anatel.exclusions.destroy',[$lista,$item]) }}">@csrf @method('DELETE')<button class="source-text-action is-danger" type="submit">Excluir</button></form>
                        </div>
                    </li>
                @endforeach
                </ul>
                {{ $exclusions->links('pagination.compact') }}
            @endif
        </section>
    </div>
</div>
@endsection
