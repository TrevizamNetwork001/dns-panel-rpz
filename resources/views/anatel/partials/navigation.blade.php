@php
    $anatelSource = request()->route('lista');
    if (!$anatelSource && isset($import)) $anatelSource = $import->lista;
@endphp
<nav class="anatel-navigation" aria-label="Navegação ANATEL">
    <a href="{{ route('listas.index') }}">Fontes</a><span aria-hidden="true">/</span>
    <a href="{{ route('anatel.dashboard') }}" @if(request()->routeIs('anatel.dashboard')) aria-current="page" @endif>Importações ANATEL</a>
    @if($anatelSource)
        <span aria-hidden="true">/</span><a href="{{ route('listas.show', $anatelSource) }}">{{ $anatelSource->nome }}</a>
        <div class="anatel-source-links">
            <a href="{{ route('anatel.history', $anatelSource) }}" @if(request()->routeIs('anatel.history')) aria-current="page" @endif>Histórico</a>
            <a href="{{ route('anatel.exclusions', $anatelSource) }}" @if(request()->routeIs('anatel.exclusions')) aria-current="page" @endif>Exclusões</a>
        </div>
    @endif
</nav>
@if(request()->routeIs('anatel.dashboard', 'anatel.preview', 'anatel.batch'))
<ol class="anatel-steps" aria-label="Etapas da importação">
    <li @if(request()->routeIs('anatel.dashboard')) aria-current="step" @endif><span>1</span><div><strong>Importar arquivos</strong><small>PDFs e planilhas da fonte</small></div></li>
    <li @if(request()->routeIs('anatel.preview', 'anatel.batch')) aria-current="step" @endif><span>2</span><div><strong>Revisar domínios</strong><small>Confira a prévia e as exclusões</small></div></li>
    <li><span>3</span><div><strong>Publicar no RPZ</strong><small>Após sua aprovação</small></div></li>
</ol>
@endif
