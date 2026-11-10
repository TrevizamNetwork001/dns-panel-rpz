@extends('layouts.app')
@section('title','Central ANATEL')
@section('content')
@include('listas.partials.admin-detail-styles')
@include('listas.partials.source-tools-styles')
@php
    $selected = $listas->firstWhere('id', (int) old('lista_id', request('lista'))) ?? $listas->first();
    $pending = $listas->where('anatel_pending_count','>',0);
    $watch = array_values(array_filter(explode(',', request('watch','')), fn($id) => ctype_digit($id)));
@endphp
<div class="source-tools anatel-central">
<div class="page-heading"><div><div class="page-eyebrow">ANATEL / PDF</div><h1>Central de importação</h1><p>Envie os arquivos, revise os domínios e publique na fonte.</p></div>@if($selected)<div class="page-actions"><a class="button button-secondary" href="{{ route('listas.show',$selected) }}">Voltar à fonte</a></div>@endif</div>
<div class="anatel-central-grid">
<section class="panel">
<div class="panel-header"><h2>Importar arquivos</h2></div>
@if($listas->isEmpty())
<div class="empty-state"><span>Crie uma fonte ANATEL para começar.</span><a href="{{ route('listas.create',['origem'=>'anatel']) }}" class="button button-primary">Criar fonte ANATEL</a></div>
@else
<form action="{{ route('anatel.dashboard.store') }}" method="POST" enctype="multipart/form-data" class="source-exclusion-form">@csrf
<div class="field-group"><label for="lista_id">Fonte de destino</label><select class="form-control" id="lista_id" name="lista_id" required>@foreach($listas as $lista)<option value="{{ $lista->id }}" @selected($selected?->id === $lista->id)>{{ $lista->nome }}</option>@endforeach</select>@error('lista_id')<span class="source-field-error">{{ $message }}</span>@enderror</div>
<div class="anatel-file-field"><label for="pdfs">PDFs ou planilhas Excel</label><input class="form-control" id="pdfs" type="file" name="pdfs[]" accept="application/pdf,.pdf,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" multiple required><p class="source-help">Até {{ config('anatel.max_files') }} arquivos · {{ (int)(config('anatel.max_pdf_kb')/1024) }} MB por arquivo.</p>@error('pdfs')<span class="source-field-error">{{ $message }}</span>@enderror @error('pdfs.*')<span class="source-field-error">{{ $message }}</span>@enderror</div>
<div class="source-actions"><button class="button button-primary" type="submit">Iniciar importação</button></div>
<p class="source-help">Os domínios só entram na fonte após sua aprovação.</p>
</form>
@endif
</section>
<section class="panel anatel-review-panel"><div class="panel-header"><h2>Revisão e publicação</h2>@if($pending->isNotEmpty())<span class="source-small-count">{{ $pending->sum('anatel_pending_count') }} pendente(s)</span>@endif</div>
<div class="anatel-pending-list">
@forelse($pending as $lista)
<div class="anatel-pending-item"><div><strong>{{ $lista->nome }}</strong><p>{{ $lista->anatel_pending_count }} arquivo(s) para revisar</p></div><a class="button button-secondary" href="{{ route('anatel.batch',$lista) }}">Revisar lote</a></div>
@empty
<div class="anatel-clear"><span class="anatel-clear-icon"><x-ui-icon name="check" size="21" /></span><div><h3>Tudo em dia</h3><p>Nenhum lote pendente. Os próximos arquivos enviados aparecerão aqui para revisão.</p></div></div>
@endforelse
</div></section>
</div>
@if($watch)
<section class="panel" id="anatel-progress" data-ids="{{ implode(',',$watch) }}" data-url="{{ url('/anatel/imports') }}">
<div class="panel-header"><h2>Processando arquivos</h2><strong id="progress-label" aria-live="polite">0%</strong></div>
<div class="anatel-progress-track"><div id="progress-bar"></div></div>
<div id="progress-result" aria-live="polite"></div>
</section>
<script>
(()=>{const box=document.getElementById('anatel-progress'),ids=box.dataset.ids.split(','),bar=document.getElementById('progress-bar'),label=document.getElementById('progress-label'),result=document.getElementById('progress-result');
async function poll(){const rows=await Promise.all(ids.map(id=>fetch(`${box.dataset.url}/${id}/status`,{headers:{Accept:'application/json'}}).then(r=>r.json())));const progress=Math.round(rows.reduce((n,r)=>n+Number(r.progress),0)/rows.length);bar.style.width=progress+'%';label.textContent=progress+'%';
result.innerHTML=rows.map(r=>`<div class="anatel-progress-item"><strong>${escapeHtml(r.filename)}</strong><span class="status-pill ${r.status==='completed'?'is-active':r.status==='awaiting_approval'?'is-warning':'is-info'}">${escapeHtml(r.status_label)}</span>${r.status==='awaiting_approval'?`<p>Prévia pronta: <b>+${r.new}</b> novos, ${r.existing} existentes, ${r.reactivated} reativados, ${r.excluded} excluídos e ${r.invalid} inválidos.</p><a class="button button-primary" href="${r.preview_url}">Revisar domínios</a>`:r.status==='completed'?`<p>Concluído em ${escapeHtml(r.finished_at)}. Total atual: <b>${r.total_active}</b>. Disponível para ${r.endpoints} servidor(es).</p>`:''}</div>`).join('');
if(rows.some(r=>!['awaiting_approval','completed','failed','blocked','rejected'].includes(r.status)))setTimeout(poll,1000);}
function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML;}poll().catch(()=>setTimeout(poll,2000));})();
</script>
@endif
<section class="panel"><div class="panel-header"><div><h2>Importações recentes <span class="source-small-count">{{ $imports->count() }}</span></h2><p>Últimos arquivos enviados para as fontes ANATEL.</p></div></div>
<ul class="source-import-list">
@forelse($imports as $import)
@php $statusClass = match($import->status) { 'completed'=>'is-active', 'awaiting_approval','blocked'=>'is-warning', 'failed','rejected'=>'is-inactive', default=>'is-info' }; @endphp
<li class="source-import-card"><div class="source-import-top"><span class="source-import-icon"><x-ui-icon name="list" size="18" /></span><div class="source-import-title"><strong>{{ $import->original_filename }}</strong><time datetime="{{ $import->created_at->toIso8601String() }}">{{ $import->created_at->format('d/m/Y H:i') }} · {{ $import->lista->nome }}</time></div><span class="status-pill {{ $statusClass }}">{{ $import->status_label }}@if(in_array($import->status,['pending','processing'])) · {{ $import->progress }}%@endif</span></div>
<div class="anatel-import-bottom"><div class="anatel-import-numbers"><span><strong>+{{ number_format($import->new_count,0,',','.') }}</strong> novos</span><span><strong>{{ number_format($import->lista->dominios_ativos_count,0,',','.') }}</strong> ativos na fonte</span></div>
@if(in_array($import->status,['awaiting_approval','rejected','failed','blocked']))<div class="anatel-import-actions"><a href="{{ route('anatel.preview',$import) }}" class="button button-secondary">{{ $import->status==='awaiting_approval'?'Revisar prévia':'Ver importação' }}</a><form method="POST" action="{{ route('anatel.preview.destroy',$import) }}">@csrf @method('DELETE')<button type="submit" class="source-text-action is-danger" onclick="return confirm('Excluir esta importação e o arquivo privado?')">Excluir</button></form></div>@endif
</div></li>
@empty
<li class="empty-state">Nenhuma importação. Envie o primeiro arquivo acima.</li>
@endforelse
</ul></section>
</div>
@endsection
