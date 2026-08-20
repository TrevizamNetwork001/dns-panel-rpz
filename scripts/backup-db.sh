#!/bin/bash
set -euo pipefail

APP_DIR="/opt/dns-panel-rpz"
DB_PATH="$APP_DIR/database/database.sqlite"
BACKUP_DIR="$APP_DIR/database/backups"
KEEP_DAYS=14

mkdir -p "$BACKUP_DIR"

TS=$(date +%Y%m%d-%H%M%S)
DEST="$BACKUP_DIR/database.sqlite.auto-$TS.bak"

sqlite3 "$DB_PATH" ".backup '$DEST'"

# remove backups automaticos (auto-*) mais antigos que KEEP_DAYS
# backups manuais (pre-*) nao sao tocados por este script
find "$BACKUP_DIR" -maxdepth 1 -name "database.sqlite.auto-*.bak" -mtime "+$KEEP_DAYS" -delete

echo "Backup criado: $DEST"
