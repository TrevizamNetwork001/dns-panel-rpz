#!/bin/sh
set -eu

database=${DB_DATABASE:-/data/database.sqlite}
backup_dir=${BACKUP_DIR:-/backups}
keep_days=${BACKUP_KEEP_DAYS:-14}
timestamp=$(date -u +%Y%m%d-%H%M%S)
destination="$backup_dir/database.sqlite.auto-$timestamp.bak"
mkdir -p "$backup_dir"
sqlite3 "$database" ".timeout 10000" ".backup '$destination'"
chmod 0660 "$destination"
find "$backup_dir" -maxdepth 1 -type f -name 'database.sqlite.auto-*.bak' -mtime "+$keep_days" -delete
echo "Backup SQLite concluido: $(basename "$destination")"
