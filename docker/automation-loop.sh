#!/bin/sh
set -eu

interval=${AUTOMATION_INTERVAL:-21600}
case "${1:-}" in
  external-sync) command="php artisan external:sync --no-interaction" ;;
  backup) command="php artisan backup:run --no-interaction" ;;
  *) echo "Automacao invalida" >&2; exit 64 ;;
esac

while :; do
    sleep "$interval" & wait $!
    $command
done
