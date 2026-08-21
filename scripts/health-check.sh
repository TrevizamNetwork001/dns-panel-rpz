#!/bin/bash
set -euo pipefail
cd /opt/dns-panel-rpz
/usr/bin/php artisan health:check >> storage/logs/health-check.log 2>&1
