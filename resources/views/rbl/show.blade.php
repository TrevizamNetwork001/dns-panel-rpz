@extends('layouts.app')
@section('title', $target->name.' — RBL Checker')
@section('content')
<div class="page-heading"><h1>{{ $target->name }}</h1><a href="{{ route('rbl.index') }}">Voltar ao RBL Checker</a></div>
<div class="panel"><h2>Dados do alvo</h2><p>{{ $target->type }}: {{ $target->value }} · Categoria: {{ $target->category ?? '—' }} · {{ $target->enabled ? 'Ativo' : 'Desativado' }}</p><p>{{ $target->description }}</p><p>Resultado da última verificação: <strong>{{ $target->last_status ?? 'unchecked' }}</strong> · {{ $target->last_checked_at?->format('d/m/Y H:i:s') ?? 'Nunca' }}</p>
@if($target->enabled)<form method="POST" action="{{ route('rbl.targets.check', $target) }}">@csrf<button class="button button-primary" type="submit">Verificar agora</button></form>@endif
<form method="POST" action="{{ route('rbl.targets.toggle', $target) }}">@csrf @method('PATCH')<button class="button" type="submit">{{ $target->enabled ? 'Desativar alvo' : 'Ativar alvo' }}</button></form>
<h2>Listas com último resultado listado</h2><p>Consulte a data de cada resultado; listas desativadas podem conter dados antigos.</p>
<ul>@forelse($listedChecks as $check)<li>{{ $check->list->name }} — {{ $check->response }} — {{ $check->checked_at->format('d/m/Y H:i:s') }}</li>@empty<li>Nenhuma.</li>@endforelse</ul></div>
<div class="panel"><h2>Histórico de checks</h2><div class="table-responsive"><table class="data-table"><thead><tr><th>Data</th><th>Lista</th><th>Valor / consulta</th><th>Status</th><th>Resposta / erro</th></tr></thead><tbody>
@forelse($checks as $check)<tr><td>{{ $check->checked_at->format('d/m/Y H:i:s') }}</td><td>{{ $check->list->name }}</td><td>{{ $check->checked_value }}<p>{{ $check->query ?? '—' }}</p></td><td>{{ $check->status }}</td><td>{{ $check->response }}<p>{{ $check->response_text }}</p><p>{{ $check->error_message }}</p></td></tr>@empty<tr><td colspan="5">Nenhuma verificação registrada.</td></tr>@endforelse
</tbody></table></div>{{ $checks->withQueryString()->links() }}</div>
<div class="panel"><h2>Eventos abertos e resolvidos</h2><div class="table-responsive"><table class="data-table"><thead><tr><th>Lista</th><th>Status</th><th>Primeira ocorrência</th><th>Última ocorrência</th><th>Resolvido em</th><th>Resposta / notas</th></tr></thead><tbody>
@forelse($events as $event)<tr><td>{{ $event->list->name }}</td><td>{{ $event->status }}</td><td>{{ $event->first_seen_at->format('d/m/Y H:i:s') }}</td><td>{{ $event->last_seen_at->format('d/m/Y H:i:s') }}</td><td>{{ $event->resolved_at?->format('d/m/Y H:i:s') ?? '—' }}</td><td>{{ $event->last_response }}<p>{{ $event->notes }}</p></td></tr>@empty<tr><td colspan="6">Nenhum evento registrado.</td></tr>@endforelse
</tbody></table></div>{{ $events->withQueryString()->links() }}</div>
@endsection
