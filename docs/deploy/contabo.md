# API server on Contabo

The Laravel API (`backend/`) runs on a Contabo VPS. Everything else is
elsewhere:

| Piece | Where | Guide |
|---|---|---|
| Storefront (`frontend/`), admin (`admin/`) | Vercel | — |
| Database | Neon (Frankfurt) | `neon.md` |
| Uploaded images | Cloudflare R2 | §6 below |
| DNS, domain, TLS in front of the API | Cloudflare | §7 below |
| API, queue worker, scheduler, Redis | **this server** | this file |

Render did the server work for us before; on Contabo it's ours. This file is
the list of what that work is.

Assumes **Ubuntu 24.04**, a server in **Contabo's Germany region** (close to
Neon in Frankfurt), and the API at **`api.goldcoasttokota.store`**.

---

## 1. Base system

```bash
apt update && apt upgrade -y
apt install -y nginx redis-server supervisor git unzip ufw \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl
# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

adduser --disabled-password --gecos "" deploy   # the app runs as this user
unattended-upgrades --help >/dev/null 2>&1 || apt install -y unattended-upgrades
```

- **Security updates:** keep `unattended-upgrades` on.
- **SSH:** keys only, no password login; disable root login once `deploy`
  (or a named admin user) can `sudo`.
- **Firewall:** 22, 80 and 443 only. Postgres is not on this server, and Redis
  must never be reachable from outside.

  ```bash
  ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw enable
  ```

## 2. Redis

The queue, the cache and sessions live in Redis, **not the database**. That's
what lets Neon sleep when the shop is quiet (see `neon.md` §6).

In `/etc/redis/redis.conf`: `bind 127.0.0.1 ::1` (the default — check it) and
set a `requirepass`. Then `systemctl restart redis-server`.

The app talks to Redis through `predis/predis` (a Composer package), so no
PHP extension is needed.

## 3. The code

```bash
sudo -u deploy git clone https://github.com/eddie-kay0462/gold-coast-tokota-store.git /var/www/tokota
cd /var/www/tokota/backend
sudo -u deploy composer install --no-dev --optimize-autoloader
sudo -u deploy cp .env.example .env
sudo -u deploy php artisan key:generate   # FIRST deploy only — see below
chown -R deploy:www-data storage bootstrap/cache
chmod -R ug+rw storage bootstrap/cache
```

⚠️ **`APP_KEY` is generated once and then kept forever.** It encrypts
sessions and other stored values; a new key logs everyone out and can make
encrypted data unreadable. Back it up in the password manager.

## 4. `backend/.env`

Production values. Secrets come from the password manager, never the repo.

```dotenv
APP_NAME="Gold Coast Tokota"
APP_ENV=production
APP_DEBUG=false
APP_KEY=                      # from key:generate, then never changed
APP_URL=https://api.goldcoasttokota.store

# The two Vercel apps. Cookie sign-in needs both under goldcoasttokota.store.
FRONTEND_URLS=https://goldcoasttokota.store,https://admin.goldcoasttokota.store
STOREFRONT_URL=https://goldcoasttokota.store
SANCTUM_STATEFUL_DOMAINS=goldcoasttokota.store,admin.goldcoasttokota.store
SESSION_DOMAIN=.goldcoasttokota.store
SESSION_SECURE_COOKIE=true

# Neon — values from neon.md §3
DB_CONNECTION=pgsql
DB_URL=
DB_DIRECT_URL=
DB_SSLMODE=require

# Redis for queue, cache, sessions
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=               # the requirepass from §2
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

# Cloudflare R2 — values from §6
MEDIA_DISK=r2
PRIVATE_MEDIA_DISK=r2-private
R2_ENDPOINT=https://<account id>.r2.cloudflarestorage.com
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=tokota-media
R2_PUBLIC_URL=https://media.goldcoasttokota.store
R2_PRIVATE_BUCKET=tokota-private

# Payments — Paystack only (CLAUDE.md)
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_PUBLIC_KEY=
PAYSTACK_SECRET_KEY=
PAYSTACK_WEBHOOK_SECRET=

# Delivery, SMS, FX — empty until the business accounts exist
YANGO_API_BASE_URL=
YANGO_API_KEY=
DHL_API_BASE_URL=
DHL_API_KEY=
FISH_AFRICA_BASE_URL=https://api.letsfish.africa
FISH_AFRICA_APP_ID=
FISH_AFRICA_APP_SECRET=
EXCHANGERATE_HOST_URL=https://api.exchangerate.host
EXCHANGERATE_HOST_KEY=

MAIL_MAILER=
MAIL_FROM_ADDRESS=hello@goldcoasttokota.store
MAIL_FROM_NAME="Gold Coast Tokota"

LOG_CHANNEL=daily
LOG_LEVEL=warning
```

Production checkout refuses to start (503) until `PAYSTACK_SECRET_KEY` is set.

## 5. nginx and PHP-FPM

`/etc/nginx/sites-available/tokota-api`:

```nginx
server {
    listen 443 ssl http2;
    server_name api.goldcoasttokota.store;

    # Cloudflare Origin Certificate (§7)
    ssl_certificate     /etc/ssl/cloudflare/api.pem;
    ssl_certificate_key /etc/ssl/cloudflare/api.key;

    root /var/www/tokota/backend/public;
    index index.php;
    client_max_body_size 12M;   # image uploads

    # Every request arrives from a Cloudflare IP. Without this, Laravel sees
    # that IP instead of the customer's, and the per-IP rate limit
    # (120/min, AppServiceProvider) is shared by everyone at once.
    include /etc/nginx/cloudflare-real-ip.conf;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known) { deny all; }
}

server {
    listen 80;
    server_name api.goldcoasttokota.store;
    return 301 https://$host$request_uri;
}
```

`/etc/nginx/cloudflare-real-ip.conf` lists Cloudflare's ranges
(https://www.cloudflare.com/ips/) and reads the real address from them:

```nginx
set_real_ip_from 173.245.48.0/20;
# ... one line per range from cloudflare.com/ips-v4 and /ips-v6 ...
real_ip_header CF-Connecting-IP;
```

Cloudflare occasionally changes the list, so refresh it now and then.

Set PHP-FPM's pool (`/etc/php/8.3/fpm/pool.d/www.conf`) to `user = deploy`,
`group = www-data`. Then:

```bash
ln -s /etc/nginx/sites-available/tokota-api /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx && systemctl restart php8.3-fpm
```

Health check: `https://api.goldcoasttokota.store/up` returns 200.

## 6. Background processes

**Queue worker** (supervisor), `/etc/supervisor/conf.d/tokota-worker.conf`:

```ini
[program:tokota-worker]
command=php /var/www/tokota/backend/artisan queue:work redis --tries=3 --max-time=3600 --sleep=3
user=deploy
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/tokota/backend/storage/logs/worker.log
```

```bash
supervisorctl reread && supervisorctl update
```

Without the worker, queued jobs never run: the FX rate stops refreshing and
expired checkout holds never give stock back.

**Scheduler** (cron, as `deploy`: `crontab -u deploy -e`):

```
* * * * * cd /var/www/tokota/backend && php artisan schedule:run >> /dev/null 2>&1
```

It dispatches `RefreshFxRate` hourly and `ReleaseExpiredReservations` every
five minutes.

## 7. Cloudflare

**R2 (images):**
1. Create two buckets: **`tokota-media`** (public) and **`tokota-private`**
   (private — leave public access off). One bucket can't be half public,
   and customers' DIY photos must not be.
2. On `tokota-media`, connect the custom domain
   **`media.goldcoasttokota.store`**. That is `R2_PUBLIC_URL`.
3. Create an R2 API token with **Object Read & Write** on just those two
   buckets. Its access key and secret are `R2_ACCESS_KEY_ID` and
   `R2_SECRET_ACCESS_KEY`; the account ID goes in `R2_ENDPOINT`.

**DNS:**

| Name | Points to | Proxy |
|---|---|---|
| `api` | the Contabo IP (A record) | **Proxied** (orange) |
| `@`, `www`, `admin` | Vercel, as Vercel's domain screen says | **DNS only** (grey). Vercel advises against proxying, which interferes with its SSL and caching |
| `media` | set up by R2 in step 2 | (managed by R2) |

**TLS for the API:** SSL/TLS mode **Full (strict)**. Create an **Origin
Certificate** for `api.goldcoasttokota.store` and install it at the paths in
the nginx config. Never use Flexible: it sends traffic to the server
unencrypted, and Laravel would think every request is plain HTTP.

**Paystack:** set the webhook URL in the Paystack dashboard to
`https://api.goldcoasttokota.store/api/v1/webhooks/paystack`.

## 8. Deploying

First deploy, then every deploy after it:

```bash
cd /var/www/tokota
sudo -u deploy git pull --ff-only
cd backend
sudo -u deploy composer install --no-dev --optimize-autoloader
sudo -u deploy php artisan migrate --force --database=pgsql_direct
sudo -u deploy php artisan config:cache
sudo -u deploy php artisan route:cache
sudo -u deploy php artisan event:cache
sudo systemctl reload php8.3-fpm
sudo -u deploy php artisan queue:restart     # the worker picks up the new code
```

Migrations use Neon's **direct** connection (`neon.md` §2); everything else
uses the pooled one.

After `config:cache`, `.env` is read only at the next cache, so **re-run
`config:cache` after any `.env` change**.

`backend/Dockerfile` dates from Render. It runs `php artisan serve`, which is
not meant for production; this guide doesn't use it.

## 9. Keeping an eye on it

- Logs: `backend/storage/logs/` (daily files) and `worker.log`.
- Uptime: point a free uptime monitor at `/up`.
- Disk: logs grow; the `daily` channel keeps 14 days by default.
- Backups: the database is backed up by Neon and the images by R2. What
  lives only on this server is `.env`; keep a copy in the password manager.

## Checklist

- [ ] Ubuntu updated, firewall on, SSH keys only
- [ ] Redis bound to localhost with a password
- [ ] Code cloned, `.env` filled in, `APP_KEY` generated once and backed up
- [ ] nginx + PHP-FPM, Cloudflare real-IP config, Origin Certificate
- [ ] `/up` returns 200 through `https://api.goldcoasttokota.store`
- [ ] Worker under supervisor, scheduler in cron
- [ ] R2 buckets, custom domain, token
- [ ] DNS records; Full (strict)
- [ ] Paystack webhook URL updated
- [ ] Storefront and admin on Vercel have
      `NUXT_PUBLIC_API_BASE=https://api.goldcoasttokota.store/api/v1`
- [ ] Smoke test: admin sign-in, a product page, a test checkout
