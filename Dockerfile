# syntax=docker/dockerfile:1
# Production image (B-33): one image for the web app, the queue worker and the scheduler.
# The role is chosen with CONTAINER_ROLE (app | queue | scheduler); see docker-compose.yml.

# --- PHP dependencies -------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# --- Frontend assets --------------------------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# --- Runtime ----------------------------------------------------------------------------------
# Ubuntu 24.04 ships PHP 8.3 and every extension the app needs as packages: no compiling or PECL.
FROM ubuntu:24.04 AS app

ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl nginx supervisor tini \
        php8.3-fpm php8.3-cli php8.3-bcmath php8.3-curl php8.3-gd php8.3-intl php8.3-mbstring \
        php8.3-mysql php8.3-pgsql php8.3-redis php8.3-xml php8.3-zip \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && sed -i 's#^error_log = .*#error_log = /proc/self/fd/2#' /etc/php/8.3/fpm/php-fpm.conf \
    && mkdir -p /run/php

COPY docker/php.ini /etc/php/8.3/fpm/conf.d/99-app.ini
COPY docker/php.ini /etc/php/8.3/cli/conf.d/99-app.ini
COPY docker/php-fpm.conf /etc/php/8.3/fpm/pool.d/www.conf
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN rm -rf docker tests node_modules .env \
    && chmod +x /usr/local/bin/entrypoint \
    && runuser -u www-data -- php artisan package:discover --ansi \
    && mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    CONTAINER_ROLE=app

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD [ "$CONTAINER_ROLE" != "app" ] || curl -fsS http://127.0.0.1:8080/up >/dev/null || exit 1

ENTRYPOINT ["/usr/bin/tini", "--", "entrypoint"]
