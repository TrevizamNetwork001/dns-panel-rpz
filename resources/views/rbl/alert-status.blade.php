@forelse($event->alerts as $alert)
<div><span class="badge" title="{{ $alert->error_message }}">{{ ['sent' => 'Alerta enviado', 'failed' => 'Alerta falhou', 'skipped' => 'Sem alerta'][$alert->status] ?? 'Sem alerta' }}</span>
<small>{{ $alert->type }} · {{ $alert->channel }} {{ $alert->sent_at?->format('d/m/Y H:i') }}</small></div>
@empty
<span class="badge">Sem alerta</span>
@endforelse
