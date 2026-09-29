<div class="panel endpoint-history-card">
    @if ($recent)
        <div class="panel-header"><div class="endpoint-card-title"><x-ui-icon name="clock" size="26" /><h2>Histórico recente</h2></div><button type="button" class="button button-secondary" data-endpoint-dialog="endpoint-history">Ver histórico completo</button></div>
    @endif
    @if (empty($logServidor))
        <div class="empty-state"><span>Nenhum evento nos últimos 30 dias — o servidor ainda não solicitou a RPZ e nenhuma lista vinculada mudou.</span></div>
    @else
        <ol class="endpoint-event-stream" aria-label="Log do servidor">
            @foreach (($recent ? array_slice($logServidor, 0, 5) : $logServidor) as $evento)
                @php
                    [$eventClass, $eventLabel] = match ($evento['tipo']) {
                        'sync' => ['sync', 'Requisição RPZ'],
                        'lista_add' => ['add', 'Adição'],
                        'lista_remove' => ['remove', 'Remoção'],
                        default => ['other', 'Evento'],
                    };
                    $eventMessage = preg_replace_callback(
                        '/(\d+) domínio\(s\)$/u',
                        fn (array $match): string => number_format((int) $match[1], 0, ',', '.').' '.((int) $match[1] === 1 ? 'domínio' : 'domínios'),
                        $evento['detalhe'],
                    );
                @endphp
                <li class="endpoint-event endpoint-event-{{ $eventClass }}">
                    <time datetime="{{ $evento['timestamp']->format($evento['tipo'] === 'sync' ? 'c' : 'Y-m-d') }}">{{ $evento['timestamp']->format('d/m/Y') }}@if ($evento['tipo'] === 'sync')<span>{{ $evento['timestamp']->format('H:i:s') }}</span>@endif</time>
                    <span class="endpoint-event-type">{{ $eventLabel }}</span>
                    <span class="endpoint-event-message">
                        @if ($evento['lista_id'])
                            <a href="{{ route('listas.historico', $evento['lista_id']) }}" class="table-primary-link">{{ $eventMessage }}</a>
                        @else
                            {{ $eventMessage }}
                        @endif
                    </span>
                    @if (! $evento['lista_id'] && $evento['meta'])
                        <span class="endpoint-event-ip endpoint-detail-mono">IP {{ $evento['meta'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>

    @endif
</div>
