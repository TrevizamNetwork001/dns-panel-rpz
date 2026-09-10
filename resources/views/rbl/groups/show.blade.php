@extends('layouts.app')
@section('title', $group->name)
@section('content')
<div class="page-heading"><h1>{{ $group->name }}</h1><a class="button" href="{{ route('rbl.groups.edit',$group) }}">Editar grupo</a></div>
@include('rbl.navigation')
<div class="panel"><p>{{ $group->category ?? 'Sem categoria' }} · {{ $group->enabled ? 'Ativo' : 'Desativado' }}</p><p>{{ $group->description }}</p><p>Total de alvos: {{ $total }} · Listados agora: {{ $listed }} · Eventos abertos: {{ $open }}</p><p>Último check: {{ $lastCheck?->checked_at?->format('d/m/Y H:i:s') ?? 'Nunca' }} · Execução: {{ $lastCheck?->rbl_run_id ?? '—' }}</p><form method="POST" action="{{ route('rbl.groups.toggle',$group) }}">@csrf @method('PATCH')<button class="button">{{ $group->enabled ? 'Desativar' : 'Ativar' }}</button></form></div>
<div class="panel"><h2>Blocos CIDR</h2><p>Blocos: {{ $scanSummary['blocks'] }} · Total estimado de IPs: {{ $scanSummary['total_ips'] }} · Verificados no ciclo: {{ $scanSummary['scanned_ips'] }} · Pendentes: {{ $scanSummary['pending_ips'] }} · Listados: {{ $scanSummary['listed_ips'] }} · Eventos abertos: {{ $open }}</p></div>
<div class="panel"><h2>Alvos do grupo</h2><table class="data-table"><thead><tr><th>Alvo</th><th>Valor monitorado</th><th>Status</th><th>Último check</th></tr></thead><tbody>@forelse($targets as $target)<tr><td><a href="{{ route('rbl.targets.show',$target) }}">{{ $target->name }}</a></td><td>{{ $target->value }}</td><td>{{ $target->last_status }}</td><td>{{ $target->last_checked_at?->format('d/m/Y H:i:s') ?? 'Nunca' }}</td></tr>@empty<tr><td colspan="4">Nenhum alvo associado.</td></tr>@endforelse</tbody></table>{{ $targets->links() }}</div>
<div class="panel"><h2>Últimos eventos do grupo</h2>@include('rbl.event-table')</div>
@endsection
