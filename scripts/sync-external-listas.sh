#!/bin/bash
set -euo pipefail
cd /opt/dns-panel-rpz
/usr/bin/php artisan external:sync >> storage/logs/external-sync.log 2>&1
