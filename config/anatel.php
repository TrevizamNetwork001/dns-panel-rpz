<?php

return [
    'python_bin' => env('ANATEL_PYTHON_BIN', base_path('.venv-anatel/bin/python')),
    'extract_timeout' => (int) env('ANATEL_EXTRACT_TIMEOUT', 120),
    'max_pdf_kb' => (int) env('ANATEL_MAX_PDF_KB', 20480),
    'max_files' => 3,
    'imap_list_id' => (int) env('ANATEL_IMAP_LIST_ID', 0),
    'imap_scan_time' => env('ANATEL_IMAP_SCAN_TIME', '08:00'),
    'publication_notices_enabled' => (bool) env('ANATEL_PUBLICATION_NOTICES_ENABLED', false),
    'smtp_host' => env('ANATEL_SMTP_HOST'),
    'smtp_port' => (int) env('ANATEL_SMTP_PORT', 465),
    'blocked_ratio' => (float) env('ANATEL_BLOCKED_RATIO', 0.10),
    'blocked_baseline' => (int) env('ANATEL_BLOCKED_BASELINE', 100),
    'legacy_feed_url' => env('ANATEL_LEGACY_FEED_URL', 'https://trevizamnetwork.com.br/dns/lista_bloqueios.txt'),
];
