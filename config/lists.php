<?php

return [
    'external_sync_enabled' => (bool) env('EXTERNAL_SYNC_ENABLED', true),
    'sync_interval_hours' => ['threatfox' => 12],
    // Protege o processo contra feeds anormalmente grandes sem bloquear feeds
    // legítimos de threat intelligence. Pode ser ajustado no ambiente.
    'external_feed_max_bytes' => (int) env('EXTERNAL_FEED_MAX_BYTES', 134217728),
];
