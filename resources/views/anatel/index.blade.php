@extends('layouts.app')
@section('title','Importar PDFs e planilhas ANATEL')
@section('content')
<div class="page-heading"><div><div class="page-eyebrow">ANATEL</div><h1>Importar PDFs e planilhas</h1><p>Atualize uma lista; endpoints já vinculados recebem os domínios na próxima sincronização.</p></div></div>
<div class="panel">
 @if($listas->isEmpty())
  <div class="empty-state"><span>Crie primeiro uma fonte do tipo ANATEL / PDF.</span><a href="{{ route('listas.create',['origem'=>'anatel']) }}" class="button button-primary">Criar fonte ANATEL</a></div>
 @else
 <form action="{{ route('anatel.dashboard.store') }}" method="POST" enctype="multipart/form-data">@csrf
  <div class="form-grid"><div class="field-group"><label for="lista_id">Lista de destino</label><select class="form-control" id="lista_id" name="lista_id" required>@foreach($listas as $lista)<option value="{{ $lista->id }}" @selected((int)request('lista')===$lista->id)>{{ $lista->nome }} — {{ number_format($lista->dominios_ativos_count,0,',','.') }} domínios — {{ $lista->servidores_count }} endpoint(s)</option>@endforeach</select></div>
  <div class="field-group"><label for="pdfs">PDFs ou planilhas Excel</label><input class="form-control" id="pdfs" type="file" name="pdfs[]" accept="application/pdf,.pdf,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" multiple required><small>Até {{ config('anatel.max_files') }} arquivos (PDF ou .xlsx), {{ (int)(config('anatel.max_pdf_kb')/1024) }} MB cada.</small></div></div>
  <button class="button button-primary" type="submit">Iniciar importação</button>
 </form>
 @endif
</div>

@foreach($listas->where('anatel_pending_count','>',0) as $lista)
<div class="panel"><div class="panel-header"><div><h2>Lote pendente · {{ $lista->nome }}</h2><p>{{ $lista->anatel_pending_count }} PDF(s) aguardando validação. A lista RPZ ainda não foi alterada.</p></div><a class="button button-primary" href="{{ route('anatel.batch',$lista) }}">Revisar lote e publicar</a></div></div>
@endforeach

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
result.innerHTML=rows.map(r=>`<div style="margin-top:12px"><strong>${escapeHtml(r.filename)}</strong> — ${escapeHtml(r.status_label)}${r.status==='awaiting_approval'?`<br>Prévia pronta: <b>+${r.new}</b> novos, ${r.existing} existentes, ${r.reactivated} reativados, ${r.excluded} excluídos e ${r.invalid} inválidos. <a class="button button-primary" href="${r.preview_url}">Revisar domínios</a>`:r.status==='completed'?`<br>Concluído em ${r.finished_at}. Total atual: <b>${r.total_active}</b>. Disponível para ${r.endpoints} endpoint(s).`:''}</div>`).join('');
if(rows.some(r=>!['awaiting_approval','completed','failed','blocked','rejected'].includes(r.status)))setTimeout(poll,1000);}
function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML;}poll().catch(()=>setTimeout(poll,2000));})();
</script>
@endif

<div class="panel"><div class="panel-header"><h2>Importações recentes</h2></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Data</th><th>Lista</th><th>Arquivo</th><th>Status</th><th>Novos</th><th>Total atual</th><th></th></tr></thead><tbody>@forelse($imports as $import)<tr><td>{{ $import->created_at->format('d/m/Y H:i') }}</td><td>{{ $import->lista->nome }}</td><td>{{ $import->original_filename }}</td><td>{{ $import->status_label }} @if(in_array($import->status,['pending','processing']))({{ $import->progress }}%)@endif</td><td>+{{ $import->new_count }}</td><td>{{ number_format($import->lista->dominios_ativos_count,0,',','.') }}</td><td style="white-space:nowrap">@if(in_array($import->status,['awaiting_approval','rejected','failed','blocked']))<a href="{{ route('anatel.preview',$import) }}" class="button button-ghost" style="min-height:auto;padding:4px 10px">Ver</a> <form style="display:inline" method="POST" action="{{ route('anatel.preview.destroy',$import) }}">@csrf @method('DELETE')<button type="submit" class="button button-ghost" style="min-height:auto;padding:4px 10px;color:var(--danger)" onclick="return confirm('Excluir esta importação e o arquivo privado?')">Excluir</button></form>@endif</td></tr>@empty<tr><td colspan="7">Nenhuma importação.</td></tr>@endforelse</tbody></table></div></div>
@endsection
