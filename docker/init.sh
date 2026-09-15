#!/bin/sh
set -eu

install -d -o www-data -g www-data -m 0770 /data /backups /var/www/html/storage/app/private/anatel \
    /var/www/html/storage/app/public /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views \
    /var/www/html/storage/logs /var/www/html/bootstrap/cache
test -e /data/database.sqlite || install -o www-data -g www-data -m 0660 /dev/null /data/database.sqlite
chown -R www-data:www-data /data /backups /var/www/html/storage /var/www/html/bootstrap/cache
find /data /backups /var/www/html/storage /var/www/html/bootstrap/cache -type d -exec chmod 0770 {} +
find /data /backups /var/www/html/storage /var/www/html/bootstrap/cache -type f -exec chmod 0660 {} +
