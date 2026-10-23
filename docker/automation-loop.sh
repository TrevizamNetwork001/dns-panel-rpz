#!/bin/sh
set -eu

# A shell waiting for the interval must exit promptly on Compose stop.
# Foreground backup/sync commands retain their normal grace period.
trap 'exit 0' TERM INT

interval=${AUTOMATION_INTERVAL:-21600}
case "${1:-}" in
  external-sync) command="php artisan external:sync --no-interaction" ;;
  backup) command="php artisan backup:run --scheduled --no-interaction" ;;
  *) echo "Automacao invalida" >&2; exit 64 ;;
esac

while :; do
    sleep "$interval" & wait $!
    $command || echo "Automacao falhou; nova tentativa no próximo intervalo." >&2
done
