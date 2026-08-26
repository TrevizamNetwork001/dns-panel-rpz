@extends('layouts.app')
@section('title','Importar PDFs ANATEL')
@section('content')
<div class="page-heading"><div><div class="page-eyebrow">ANATEL</div><h1>Importar PDFs</h1><p>Atualize uma lista; endpoints já vinculados recebem os domínios na próxima sincronização.</p></div></div>
<div class="panel">
 @if($listas->isEmpty())
  <div class="empty-state"><span>Crie primeiro uma fonte do tipo ANATEL / PDF.</span><a href="{{ route('listas.create',['origem'=>'anatel']) }}" class="button button-primary">Criar fonte ANATEL</a></div>
 @else
 <form action="{{ route('anatel.dashboard.store') }}" method="POST" enctype="multipart/form-data">@csrf
  <div class="form-grid"><div class="field-group"><label for="lista_id">Lista de destino</label><select class="form-control" id="lista_id" name="lista_id" required>@foreach($listas as $lista)<option value="{{ $lista->id }}" @selected((int)request('lista')===$lista->id)>{{ $lista->nome }} — {{ number_format($lista->dominios_ativos_count,0,',','.') }} domínios — {{ $lista->servidores_count }} endpoint(s)</option>@endforeach</select></div>
  <div class="field-group"><label for="pdfs">PDFs</label><input class="form-control" id="pdfs" type="file" name="pdfs[]" accept="application/pdf,.pdf" multiple required><small>Até {{ config('anatel.max_files') }} PDFs, {{ (int)(config('anatel.max_pdf_kb')/1024) }} MB cada.</small></div></div>
  <button class="button button-primary" type="submit">Iniciar importação</button>
 </form>
 @endif
</div>

@php $watch=array_values(array_filter(explode(',',request('watch','')),fn($id)=>ctype_digit($id))); @endphp
@if($watch)
<div class="panel" id="anatel-progress" data-ids="{{ implode(',',$watch) }}" data-url="{{ url('/anatel/imports') }}">
 <div class="panel-header"><h2>Processando</h2><strong id="progress-label">0%</strong></div>
 <div style="height:14px;background:var(--surface-muted,#e5e7eb);border-radius:8px;overflow:hidden"><div id="progress-bar" style="height:100%;width:0;background:var(--primary,#2563eb);transition:width .4s"></div></div>
 <div id="progress-result" style="margin-top:16px"></div>
</div>
<script>
(()=>{const box=document.getElementById('anatel-progress'),ids=box.dataset.ids.split(','),bar=document.getElementById('progress-bar'),label=document.getElementById('progress-label'),result=document.getElementById('progress-result');
async function poll(){const rows=await Promise.all(ids.map(id=>fetch(`${box.dataset.url}/${id}/status`,{headers:{Accept:'application/json'}}).then(r=>r.json())));const progress=Math.round(rows.reduce((n,r)=>n+Number(r.progress),0)/rows.length);bar.style.width=progress+'%';label.textContent=progress+'%';
result.innerHTML=rows.map(r=>`<div style="margin-top:12px"><strong>${escapeHtml(r.filename)}</strong> — ${escapeHtml(r.status)}${r.status==='completed'?`<br>Concluído em ${r.finished_at}: <b>+${r.new}</b> novos, ${r.existing} existentes, ${r.reactivated} reativados, ${r.excluded} excluídos, ${r.invalid} inválidos. Total atual: <b>${r.total_active}</b>. Disponível para ${r.endpoints} endpoint(s) vinculado(s) na próxima sincronização. <a href="${r.new_url}">Ver novos domínios</a>`:''}</div>`).join('');
if(rows.some(r=>!['completed','failed','blocked'].includes(r.status)))setTimeout(poll,1000);else setTimeout(()=>location.href='{{ route('anatel.dashboard') }}',5000);}
function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML;}poll().catch(()=>setTimeout(poll,2000));})();
</script>
@endif

<div class="panel"><div class="panel-header"><h2>Importações recentes</h2></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Data</th><th>Lista</th><th>Arquivo</th><th>Status</th><th>Novos</th><th>Total atual</th></tr></thead><tbody>@forelse($imports as $import)<tr><td>{{ $import->created_at->format('d/m/Y H:i') }}</td><td>{{ $import->lista->nome }}</td><td>{{ $import->original_filename }}</td><td>{{ strtoupper($import->status) }} @if(in_array($import->status,['pending','processing']))({{ $import->progress }}%)@endif</td><td>+{{ $import->new_count }}</td><td>{{ number_format($import->lista->dominios_ativos_count,0,',','.') }}</td></tr>@empty<tr><td colspan="6">Nenhuma importação.</td></tr>@endforelse</tbody></table></div></div>
@endsection
