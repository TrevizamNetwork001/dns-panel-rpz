@php
    $labels = ['new'=>'Novo','existing'=>'Existente','reactivated'=>'Reativar','excluded'=>'Excluído'];
    $categories = [''=>'Todos','new'=>'Novos','existing'=>'Existentes','reactivated'=>'Reativados','excluded'=>'Excluídos'];
@endphp
<nav class="source-filter-tabs" aria-label="Filtrar domínios por classificação">
@foreach($categories as $value=>$label)
    @php $tabValue=$value===''?null:$value; @endphp
    <a class="source-filter-tab {{ $filter===$tabValue?'is-selected':'' }}" @if($filter===$tabValue) aria-current="page" @endif href="{{ route($reviewRoute,[$reviewTarget,'result'=>$tabValue]) }}">{{ $label }} ({{ number_format($value===''?$reviewCounts->sum():($reviewCounts[$value]??0),0,',','.') }})</a>
@endforeach
</nav>
<ul class="anatel-domain-list">
@forelse($domains as $domain)
    <li><code>{{ $domain->domain }}</code><div><span class="anatel-result-badge anatel-result-{{ $domain->result }}">{{ $labels[$domain->result]??$domain->result }}</span>@if($showOccurrences)<span class="source-tool-caption">{{ $domain->occurrences }} ocorrência(s)</span>@endif</div></li>
@empty
    <li class="anatel-domain-empty">Nenhum domínio nessa categoria.</li>
@endforelse
</ul>
{{ $domains->links('pagination.compact') }}
