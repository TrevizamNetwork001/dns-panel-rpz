#!/bin/sh
# Only process PDF preview jobs in the recovered TEST database.
set -eu
test "${DB_DATABASE:-}" = /data/database.sqlite
test -s /data/database.sqlite
while true; do
    sqlite3 /data/database.sqlite '.timeout 10000' \
        "UPDATE jobs SET queue='anatel-preview' WHERE queue='default' AND reserved_at IS NULL AND json_extract(payload, '\$.displayName')='App\Jobs\ProcessAnatelImport';"
    php artisan queue:work --queue=anatel-preview --once --sleep=2 --tries=1 --timeout=360
done
