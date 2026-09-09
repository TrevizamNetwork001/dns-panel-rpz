<div class="table-responsive"><table class="data-table"><thead><tr><th>Alvo</th><th>Valor</th><th>RBL</th><th>Status</th><th>Primeira detecção</th><th>Última detecção</th><th>Resolução</th><th>Duração aproximada</th><th>Última resposta listed</th></tr></thead><tbody>
@forelse($events as $event)
<tr><td>@if($event->target)<a href="{{ route('rbl.targets.show', $event->target) }}">{{ $event->target->name }}</a>@else — @endif</td><td>{{ $event->target?->value ?? '—' }}</td><td>{{ $event->list?->name ?? '—' }}</td><td>{{ $event->status }}</td><td>{{ $event->first_seen_at?->format('d/m/Y H:i:s') ?? '—' }}</td><td>{{ $event->last_seen_at?->format('d/m/Y H:i:s') ?? '—' }}</td><td>{{ $event->resolved_at?->format('d/m/Y H:i:s') ?? '—' }}</td><td>{{ $event->durationMinutes() }} min</td><td>{{ $event->last_response ?? '—' }}</td></tr>
@empty<tr><td colspan="9">Nenhum evento encontrado.</td></tr>@endforelse
</tbody></table></div>
