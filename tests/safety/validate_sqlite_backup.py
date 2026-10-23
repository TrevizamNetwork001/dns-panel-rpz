"""Validate a backup made ONLY from a disposable SQLite copy. No live mounts."""
import gzip
import hashlib
import json
from pathlib import Path
import shutil
import sqlite3
import sys
import tempfile


def digest(connection):
    result = hashlib.sha256()
    for line in connection.iterdump():
        result.update(line.encode())
        result.update(b'\n')
    return result.digest()


if __name__ == '__main__':
    source = Path(sys.argv[1]).resolve()
    backup = Path(sys.argv[2]).resolve()
    if str(source) != '/audit/disposable.sqlite' or backup.parent != Path('/audit/backup-test'):
        raise SystemExit('Only disposable laboratory paths are accepted')
    with tempfile.TemporaryDirectory(prefix='rpz-restore-', dir='/audit') as directory:
        restored = Path(directory) / 'restored.sqlite'
        with gzip.open(backup, 'rb') as compressed, restored.open('wb') as output:
            shutil.copyfileobj(compressed, output)
        with sqlite3.connect(f'file:{source}?mode=ro', uri=True) as original, \
                sqlite3.connect(f'file:{restored}?mode=ro', uri=True) as recovery:
            integrity = recovery.execute('PRAGMA integrity_check').fetchone()[0]
            foreign_keys = recovery.execute('PRAGMA foreign_key_check').fetchall()
            domains = recovery.execute('SELECT count(*) FROM dominios').fetchone()[0]
            same_content = digest(original) == digest(recovery)
            if integrity != 'ok' or foreign_keys or domains != 709678 or not same_content:
                raise SystemExit('Backup validation failed')
            print(json.dumps({'integrity': integrity, 'foreign_key_violations': 0,
                              'domains': domains, 'all_sql_content_equal': same_content}))
