# syntax=docker/dockerfile:1
#
# Production image for the API, run by docker-compose.prod.yml on the Contabo
# box (docs/deploy/contabo.md). Two targets from one build:
#
#   app  PHP-FPM with the Laravel code. The same image runs as the FPM server,
#        the queue worker and the scheduler; only the command differs.
#   web  nginx serving public/ and handing PHP to `app:9000`.
#
# The .env is not baked in. Compose mounts it at run time, and the entrypoint
# caches config from it on every start.

FROM php:8.3-fpm-alpine AS app

# pdo_pgsql for Neon; pcntl so queue:work stops cleanly between jobs on
# SIGTERM (deploys, --max-time); the rest are what composer.json's packages
# use. opcache ships with the base image. Built against libpq-dev rather than
# postgresql-dev, which drags in ~600 MB of clang/LLVM for no benefit.
RUN apk add --no-cache icu-libs libzip libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpq-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip bcmath pcntl \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-tokota.ini"

WORKDIR /var/www/html

# Dependencies first, so a code-only change reuses this layer.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/app/public storage/app/private storage/logs \
       storage/framework/cache/data storage/framework/sessions storage/framework/views \
       bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/tokota-entrypoint
RUN chmod +x /usr/local/bin/tokota-entrypoint

USER www-data
ENTRYPOINT ["tokota-entrypoint"]
CMD ["php-fpm"]


FROM nginx:1.27-alpine AS web

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
