#!/bin/sh
set -e

# Every container (FPM, worker, scheduler) has its own copy of bootstrap/cache,
# so each caches from the mounted .env as it starts. A changed .env therefore
# takes effect on the next `docker compose up -d`, with no separate step.
if [ ! -f .env ]; then
    echo "tokota: no .env mounted at /var/www/html/.env" >&2
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
