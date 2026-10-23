#!/bin/sh
set -eu
# Hard-disabled by compose.contabo.yml, even if its profile is selected.
# Enabling this requires a separately reviewed Compose change, not an env-file edit.
if [ "${CONTABO_AUTOMATION_APPROVED:-0}" != 1 ]; then
    echo 'Automacoes bloqueadas: proposta de preparacao, sem autorizacao de producao.' >&2
    exit 64
fi
case "${1:-}" in
    worker) exec php artisan queue:work database --sleep=1 --tries=3 --timeout=360 --max-time=3600 ;;
    scheduler) exec php artisan schedule:work --no-interaction ;;
    backup) exec /usr/local/bin/rpz-automation backup ;;
    *) echo 'Servico invalido' >&2; exit 64 ;;
esac
