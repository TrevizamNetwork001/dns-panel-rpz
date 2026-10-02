<div class="panel endpoint-history-card">
    @if ($recent)
        <div class="panel-header"><div class="endpoint-card-title"><x-ui-icon name="clock" size="26" /><h2>Histórico recente</h2></div><button type="button" class="button button-secondary" data-endpoint-dialog="endpoint-history">Ver histórico completo</button></div>
    @endif
    @if (empty($logServidor))
        <div class="empty-state"><span>Nenhum evento nos últimos 30 dias — o servidor ainda não solicitou a RPZ, nenhuma zona foi publicada e nenhuma lista vinculada mudou.</span></div>
    @else
        <ol class="endpoint-event-stream" aria-label="Log do servidor">
            @foreach (($recent ? array_slice($logServidor, 0, 5) : $logServidor) as $evento)
                @php
                    [$eventClass, $eventLabel] = match ($evento['tipo']) {
                        'sync' => ['sync', 'Zona consultada'],
                        'lista_add' => ['add', 'Domínios adicionados'],
                        'lista_remove' => ['remove', 'Domínios removidos'],
                        'publish' => ['sync', 'Zona atualizada'],
                        'notify_ok' => ['add', 'Servidor notificado'],
                        'notify_falhou' => ['remove', 'Notificação falhou'],
                        default => ['other', 'Evento'],
                    };
                    $comHorario = in_array($evento['tipo'], ['sync', 'publish', 'notify_ok', 'notify_falhou'], true);
                    $eventMessage = preg_replace_callback(
                        '/(\d+) domínio\(s\)$/u',
                        fn (array $match): string => number_format((int) $match[1], 0, ',', '.').' '.((int) $match[1] === 1 ? 'domínio' : 'domínios'),
                        $evento['detalhe'],
                    );
                    $eventMessage = match ($evento['tipo']) {
                        'sync' => preg_replace('/^RPZ solicitada/u', 'Zona consultada', $eventMessage),
                        'publish' => preg_replace('/^Zona publicada/u', 'Zona atualizada', preg_replace('/\s*,\s*serial\s+\d+/iu', '', $eventMessage)),
                        'notify_ok' => 'Servidor notificado de que há uma nova versão da zona',
                        'notify_falhou' => match (true) {
                            str_starts_with($eventMessage, 'Aviso enviado') => 'Servidor notificado de que há uma nova versão da zona — sem confirmação',
                            str_starts_with($eventMessage, 'Servidor recusou') => 'Servidor não confirmou a notificação',
                            str_starts_with($eventMessage, 'Aviso automático não habilitado') => 'Notificação do servidor não habilitada',
                            default => 'Falha ao notificar o servidor',
                        },
                        default => $eventMessage,
                    };
                @endphp
                <li class="endpoint-event endpoint-event-{{ $eventClass }}">
                    <time datetime="{{ $evento['timestamp']->format($comHorario ? 'c' : 'Y-m-d') }}">{{ $evento['timestamp']->format('d/m/Y') }}@if ($comHorario)<span>{{ $evento['timestamp']->format('H:i:s') }}</span>@endif</time>
                    <span class="endpoint-event-type">{{ $eventLabel }}</span>
                    <span class="endpoint-event-message">
                        @if ($evento['lista_id'])
                            <a href="{{ route('listas.historico', $evento['lista_id']) }}" class="table-primary-link">{{ $eventMessage }}</a>
                        @else
                            {{ $eventMessage }}
                        @endif
                    </span>
                    @if (! $evento['lista_id'] && $evento['meta'] && $evento['tipo'] !== 'sync')
                        <span class="endpoint-event-ip">{{ $evento['meta'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>

    @endif
</div>
