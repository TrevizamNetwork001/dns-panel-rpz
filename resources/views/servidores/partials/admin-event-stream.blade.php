<div class="panel details-card-wide">
    <div class="panel-header">
        <div><h2>Log do servidor</h2><span class="endpoint-detail-note">Últimos 30 dias</span></div>
        <span class="endpoint-detail-note">Sincronizações + listas</span>
    </div>
    <p class="endpoint-detail-note">Eventos recentes de sincronização e alterações nas fontes vinculadas.</p>
    <p class="endpoint-detail-note">Remoções representam desativação da regra, não exclusão do histórico.</p>
    @if (empty($logServidor))
        <div class="empty-state"><span>Nenhum evento nos últimos 30 dias — o servidor ainda não sincronizou e nenhuma lista vinculada mudou.</span></div>
    @else
        <ol class="endpoint-event-stream" aria-label="Log do servidor">
            @foreach ($logServidor as $evento)
                @php
                    [$eventClass, $eventLabel] = match ($evento['tipo']) {
                        'sync' => ['sync', 'Sincronização'],
                        'lista_add' => ['add', 'Adição'],
                        'lista_remove' => ['remove', 'Remoção'],
                        default => ['other', 'Evento'],
                    };
                    $eventMessage = preg_replace_callback(
                        '/(\d+) (domínios entregues|domínio\(s\))$/u',
                        fn (array $match): string => number_format((int) $match[1], 0, ',', '.').' '.($match[2] === 'domínio(s)' ? ((int) $match[1] === 1 ? 'domínio' : 'domínios') : $match[2]),
                        $evento['detalhe'],
                    );
                @endphp
                <li class="endpoint-event endpoint-event-{{ $eventClass }}">
                    <time datetime="{{ $evento['timestamp']->format($evento['tipo'] === 'sync' ? 'c' : 'Y-m-d') }}">{{ $evento['timestamp']->format($evento['tipo'] === 'sync' ? 'd/m/Y H:i:s' : 'd/m/Y') }}</time>
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
        <p class="endpoint-detail-note">Mostrando os últimos 80 eventos. Detalhe domínio-por-domínio disponível no histórico de cada fonte.</p>
    @endif
</div>
