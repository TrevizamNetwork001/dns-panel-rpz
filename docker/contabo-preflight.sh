#!/bin/sh
# Read-only checks: never create an empty DB, fix permissions or migrate.
set -eu

test -n "${APP_KEY:-}" || { echo 'APP_KEY original ausente' >&2; exit 1; }
test "${DB_DATABASE:-}" = /data/database.sqlite || exit 1
test -s /data/database.sqlite || { echo 'SQLite restaurado ausente' >&2; exit 1; }
test "$(sqlite3 -readonly /data/database.sqlite 'PRAGMA integrity_check;')" = ok
# -readonly is essential: no WAL-mode configuration on the recovered file.
test -z "$(sqlite3 -readonly /data/database.sqlite 'PRAGMA foreign_key_check;')"
test "$(sqlite3 -readonly /data/database.sqlite 'SELECT count(*) FROM dominios;')" -ge 709678
for path in /var/www/html/storage/app/private /var/www/html/storage/app/private/anatel \
    /var/www/html/storage/app/public /var/www/html/storage/framework /var/www/html/bootstrap/cache /backups
do
    test -d "$path" && test -r "$path" && test -x "$path" || {
        echo 'Storage incompleto ou sem permissao; requer revisao manual.' >&2
        exit 1
    }
done
# Cached configuration could silently override the safety flags or original key.
test ! -e /var/www/html/bootstrap/cache/config.php || {
    echo 'Cache de configuracao persistido requer revisao offline.' >&2
    exit 1
}
php /opt/contabo/preflight.php
found_backup=0
for backup in /backups/database.sqlite.auto-*.bak.gz; do
    test -f "$backup" || continue
    gzip -t "$backup"
    found_backup=1
done
test "$found_backup" -eq 1 || { echo 'Backup local validado ausente.' >&2; exit 1; }
echo 'Preflight somente leitura concluido; restauracao completa ainda exige homologacao.'
