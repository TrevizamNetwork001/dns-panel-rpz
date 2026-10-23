<?php

return [
    'disk_path' => env('HEALTH_DISK_PATH', '/'),
    'certificate_path' => env('HEALTH_CERT_PATH', '/etc/letsencrypt/live/rpz.trevizamnetwork.com.br/cert.pem'),
    'url' => env('HEALTH_URL', rtrim(env('APP_URL', 'https://rpz.trevizamnetwork.com.br'), '/').'/up'),
];
