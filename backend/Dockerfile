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

# Migrations run on boot rather than in a separate Render preDeploy step —
# keeps the blueprint in render.yaml simple for the initial deployment.
CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
