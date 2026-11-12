#!/bin/sh
set -eu
php -r '$key=getenv("APP_KEY"); if (!str_starts_with($key ?: "", "base64:") || strlen(base64_decode(substr($key,7),true) ?: "") !== 32) { fwrite(STDERR,"APP_KEY inválida: use o instalador para gerar a chave inicial.\n"); exit(1); }'
/usr/local/bin/rpz-init
exec su-exec www-data php artisan migrate --force --no-interaction
