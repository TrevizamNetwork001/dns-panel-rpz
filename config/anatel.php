<?php

return [
    'python_bin' => env('ANATEL_PYTHON_BIN', base_path('.venv-anatel/bin/python')),
    'extract_timeout' => (int) env('ANATEL_EXTRACT_TIMEOUT', 120),
    'max_pdf_kb' => (int) env('ANATEL_MAX_PDF_KB', 20480),
    'max_files' => 3,
    'blocked_ratio' => (float) env('ANATEL_BLOCKED_RATIO', 0.10),
    'blocked_baseline' => (int) env('ANATEL_BLOCKED_BASELINE', 100),
    'legacy_feed_url' => env('ANATEL_LEGACY_FEED_URL', 'https://trevizamnetwork.com.br/dns/lista_bloqueios.txt'),
];
