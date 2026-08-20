#!/bin/bash
set -euo pipefail
cd /opt/dns-panel-rpz
/usr/bin/php artisan urlhaus:sync >> storage/logs/urlhaus-sync.log 2>&1
