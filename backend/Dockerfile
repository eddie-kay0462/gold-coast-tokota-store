FROM php:8.3-cli-alpine

RUN apk add --no-cache postgresql-dev libzip-dev zip unzip git \
    && docker-php-ext-install pdo pdo_pgsql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan config:clear

EXPOSE 8000

# `storage:link` is not optional: public/storage is gitignored, so without it
# every image uploaded through the admin media library 404s. It is idempotent
# and cheap, so it runs on every boot rather than being a step someone has to
# remember.
#
# Migrations run here rather than in a Render preDeploy step to keep the
# blueprint simple. Note this means only the *web* service should migrate —
# the worker and scheduler run the same image with their own dockerCommand,
# which overrides this CMD, so they never race it.
CMD ["sh", "-c", "php artisan migrate --force && php artisan storage:link || true; php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
