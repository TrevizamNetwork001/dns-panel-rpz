FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --no-interaction --optimize --classmap-authoritative

FROM php:8.4.12-fpm-alpine AS app
RUN apk add --no-cache ca-certificates curl fcgi icu-libs libzip libxml2 py3-pip py3-virtualenv python3 sqlite \
    && apk add --no-cache --virtual .build-deps curl-dev icu-dev libzip-dev libxml2-dev linux-headers oniguruma-dev sqlite-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" bcmath curl intl mbstring opcache pcntl pdo_sqlite sockets zip \
    && php -m | grep -Eiq '^dom$' \
    && php -m | grep -Eiq '^simplexml$' \
    && php -m | grep -Eiq '^xml$' \
    && apk del .build-deps
COPY docker/requirements-anatel.txt /tmp/requirements-anatel.txt
RUN python3 -m venv /opt/anatel-venv \
    && /opt/anatel-venv/bin/pip install --no-cache-dir --disable-pip-version-check -r /tmp/requirements-anatel.txt \
    && rm /tmp/requirements-anatel.txt
WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-rpz.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-rpz.conf
COPY docker/init.sh /usr/local/bin/rpz-init
COPY docker/backup.sh /usr/local/bin/rpz-backup
COPY docker/automation-loop.sh /usr/local/bin/rpz-automation
RUN chmod 0755 /usr/local/bin/rpz-* \
    && ln -sfn ../storage/app/public public/storage
USER www-data
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 CMD SCRIPT_NAME=/fpm-ping SCRIPT_FILENAME=/fpm-ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1
CMD ["php-fpm", "-F"]

FROM nginxinc/nginx-unprivileged:1.28.0-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor --chown=nginx:nginx /app/public /var/www/html/public
RUN ln -sfn ../storage/app/public /var/www/html/public/storage
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 CMD wget -q -O /dev/null http://127.0.0.1:8080/up || exit 1
