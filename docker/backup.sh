#!/bin/sh
set -eu

database=${DB_DATABASE:-/data/database.sqlite}
backup_dir=${BACKUP_DIR:-/backups}
keep_days=${BACKUP_KEEP_DAYS:-14}
timestamp=$(date -u +%Y%m%d-%H%M%S)
destination="$backup_dir/database.sqlite.auto-$timestamp.bak"
mkdir -p "$backup_dir"
umask 0007
trap 'rm -f "$destination.partial" "$destination.gz.partial"' EXIT HUP INT TERM
sqlite3 "$database" ".timeout 10000" ".backup '$destination.partial'"
gzip -c "$destination.partial" > "$destination.gz.partial"
gzip -t "$destination.gz.partial"
mv "$destination.gz.partial" "$destination.gz"
find "$backup_dir" -maxdepth 1 -type f \( -name 'database.sqlite.auto-*.bak' -o -name 'database.sqlite.auto-*.bak.gz' \) -mtime "+$keep_days" -delete
echo "Backup SQLite concluido: $(basename "$destination.gz")"
