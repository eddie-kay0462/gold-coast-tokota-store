# API server on Contabo

The Laravel API (`backend/`) runs in Docker on a Contabo VPS. Everything
else is elsewhere:

| Piece | Where | Guide |
|---|---|---|
| Storefront (`frontend/`), admin (`admin/`) | Vercel | — |
| Database | Neon (Frankfurt) | `neon.md` |
| Uploaded images | Cloudflare R2 | §6 below |
| DNS for the domain | Cloudflare | §6 below |
| API, queue worker, scheduler, Redis | **this server, in Docker** | this file |

Render did the server work for us before; on Contabo it's ours. This file is
the list of what that work is.

## 0. The box is shared with IRIS

This is **not a fresh server.** IRIS production already runs on it
(`207.180.237.21`, Ubuntu 24.04, EU region), set up by IRIS's
`deploy/bootstrap.sh`:

- ufw and fail2ban, SSH key-only as the `iris` user, root login off
- Docker with log rotation, and IRIS's Compose stack in `/opt/iris`:
  **Caddy** (the only container bound to the public ports 80/443, TLS by
  ACME), `api1-3`, `worker`, `recommender`, `log-alerts`, `docker-proxy`
- a 4 GB swapfile; 8 GB RAM, most of it free

Tokota is a second Compose project beside it (`backend/docker-compose.prod.yml`,
project name `tokota`):

```
internet ──443──▶ Caddy (IRIS stack, the one public edge)
                    ├── iris-api.1nri.store        ──▶ IRIS containers
                    └── api.goldcoasttokota.store  ──▶ tokota-api:80
                                                         │  (shared `edge` network)
   ┌──────────────────── tokota stack ───────────────────┼───────────┐
   │  web (nginx) ◀──────────────────────────────────────┘           │
   │    └── app (php-fpm :9000) ──▶ Neon, Paystack, R2 (internet)    │
   │  worker (queue:work)   scheduler (schedule:work)   redis        │
   └─────────────────────────────────────────────────────────────────┘
```

- **Nothing in the tokota stack publishes a port.** (Docker's published
  ports bypass ufw, which is why IRIS publishes nothing but Caddy.) The only
  way in is the `edge` network, which only Caddy and Tokota's `web` join.
  IRIS's internal network stays private from Tokota, and the other way round.
- Tokota has its own `.env`. Nothing is added to `/opt/iris/.env`.
- The IRIS side changes in two places: Caddy joins `edge`, and the Caddyfile
  gets a site block (§5). **Recreating Caddy briefly drops traffic for both
  apps**, so do it in a quiet window and with the IRIS owner's go-ahead.
- Don't touch ufw, SSH config or users. Docker is already installed.

Commands below run as `iris` from `/opt/tokota/backend`. If `iris` isn't in
the `docker` group, prefix the `docker` commands with `sudo`.

The images are:

| Image | Built from | Runs as |
|---|---|---|
| `tokota-api` | `backend/Dockerfile`, target `app` (PHP 8.3 FPM, Alpine) | `app`, `worker`, `scheduler`, one command each |
| `tokota-web` | the same Dockerfile, target `web` (nginx with `public/`) | `web` |
| `redis:7.4-alpine` | upstream | `redis`, append-only file in the `redis-data` volume so queued jobs survive a restart |

Every app container caches config, routes and events from the mounted
`.env` as it starts (`backend/docker/entrypoint.sh`).

---

## 1. One-time setup on the box

```bash
# The network Caddy and Tokota's nginx share. Both stacks declare it
# external, so it must exist before either starts. (IRIS's deploy.sh creates
# it too if missing; this guard is safe to repeat.)
docker network inspect edge >/dev/null 2>&1 || docker network create edge

sudo mkdir -p /opt/tokota && sudo chown iris:iris /opt/tokota
git clone https://github.com/eddie-kay0462/gold-coast-tokota-store.git /opt/tokota
```

If the repository is private, give `iris` a read-only GitHub deploy key
first (`ssh-keygen -t ed25519 -f ~/.ssh/tokota_deploy`, add the `.pub` under
the repo's Settings → Deploy keys) and clone the `git@github.com:` URL
instead.

## 2. Build

```bash
cd /opt/tokota/backend
docker compose -f docker-compose.prod.yml build
```

The first build takes a few minutes because it compiles the PHP extensions.
Later builds reuse those layers.

## 3. `backend/.env`

```bash
cp .env.example .env
sudo chgrp 82 .env && chmod 640 .env    # 82 = www-data inside the Alpine image
```

The containers run as `www-data` (uid/gid 82) and read `.env` through a
read-only mount, so the file needs that group. Nobody else on the host can
read it.

Generate the key **once**:

```bash
docker compose -f docker-compose.prod.yml run --rm --no-deps \
  --entrypoint php app artisan key:generate --show
```

Paste the printed `base64:…` value into `APP_KEY`. ⚠️ **`APP_KEY` is kept
forever.** It encrypts sessions and other stored values; a new key logs
everyone out and can make encrypted data unreadable. Back it up in the
password manager.

Then set the production values over the copied `.env.example`. Secrets come
from the password manager, never the repo or chat.

```dotenv
APP_NAME="Gold Coast Tokota"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...            # from above, then never changed
APP_URL=https://api.goldcoasttokota.store

# The two Vercel apps. Cookie sign-in needs both under goldcoasttokota.store.
FRONTEND_URLS=https://goldcoasttokota.store,https://admin.goldcoasttokota.store
STOREFRONT_URL=https://goldcoasttokota.store
ADMIN_URL=https://admin.goldcoasttokota.store
SANCTUM_STATEFUL_DOMAINS=goldcoasttokota.store,admin.goldcoasttokota.store
SESSION_DOMAIN=.goldcoasttokota.store
SESSION_SECURE_COOKIE=true

# PHP-FPM is reached only by the stack's own nginx, wherever Docker's address
# pool puts it, so trusting the private ranges means "trust our nginx". It
# forwards the X-Forwarded-For/-Proto that Caddy set, which is how Laravel
# learns the customer's IP and that the request was https.
TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16

# Neon — the pooled and direct strings from neon.md §2/§3
DB_CONNECTION=pgsql
DB_URL=
DB_DIRECT_URL=
DB_SSLMODE=require

# Redis: the `redis` service. It's on the stack's private network only, so no
# password.
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
SHIPPING_QUEUE=shipping       # the worker drains `shipping` first

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

# Couriers stay off until the business accounts exist (see .env.example for
# the full list of SHIPPING_*, YANGO_* and DHL_* settings)
YANGO_ENABLED=false
DHL_ENABLED=false
OPS_ALERT_EMAIL=

# SMS and FX
FISH_AFRICA_BASE_URL=https://api.letsfish.africa
FISH_AFRICA_APP_ID=
FISH_AFRICA_APP_SECRET=
EXCHANGERATE_HOST_URL=https://api.exchangerate.host
EXCHANGERATE_HOST_KEY=

MAIL_MAILER=
MAIL_FROM_ADDRESS=hello@goldcoasttokota.store
MAIL_FROM_NAME="Gold Coast Tokota"

# To `docker logs`; Docker's log rotation (set up by IRIS) keeps them in check
LOG_CHANNEL=stderr
LOG_LEVEL=warning
```

Production checkout refuses to start (503) until `PAYSTACK_SECRET_KEY` is set.

Check the database connection:

```bash
docker compose -f docker-compose.prod.yml run --rm --no-deps app \
  php artisan migrate:status --database=pgsql_direct
```

Neon is already migrated and seeded (`neon.md` §4–5), so this should list
every migration as `Ran`.

**After any later `.env` change:**
`docker compose -f docker-compose.prod.yml up -d --force-recreate`. A plain
`restart` can keep the old file: it's a single-file bind mount, and editors
that save by replacing the file leave the container holding the old one.

## 4. Start the stack

```bash
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml ps        # web shows (healthy)
docker compose -f docker-compose.prod.yml exec web wget -qO- http://127.0.0.1/up >/dev/null && echo ok
```

What each container does if it stops:
- **worker**: queued jobs never run. The FX rate stops refreshing, expired
  checkout holds never give stock back, and courier bookings are never made
  or followed. It works `shipping` before `default`, because a Yango price
  offer lapses 10 minutes after it's made, and exits every hour to be
  restarted fresh.
- **scheduler**: dispatches `RefreshFxRate` hourly, `ReleaseExpiredReservations`
  every five minutes, `PollActiveShipments` every minute and
  `PruneOrphanedBookingUploads` daily at 03:30. Without the poller, booked
  couriers are never confirmed.

All of them restart on their own (`restart: unless-stopped`), including after
a reboot.

## 5. The Caddy route (IRIS side)

Needs the IRIS owner's go-ahead. The change is drafted in the IRIS repo:

- the `caddy` service in IRIS's `docker-compose.yml` joins the `edge`
  network (alongside the networks it's already on), declared at the top level
  as `edge: { external: true }`
- a site block in `/opt/iris/Caddyfile`:

  ```caddy
  api.goldcoasttokota.store {
  	encode zstd gzip
  	reverse_proxy tokota-api:80
  }
  ```

Caddy obtains the certificate itself by ACME, as it does for IRIS, and
forwards `X-Forwarded-For`/`-Proto`/`-Host` by default (replacing any a client
sent). The block has **no `log` directive on purpose**: IRIS's `log-alerts`
sidecar pages Telegram on any 502/503/504 access line without naming the
host, so Tokota's errors would read as IRIS outages. `docker compose logs`
in the tokota stack has Tokota's own logs.

**Order matters.** If Caddy goes live before DNS points at the box, it keeps
retrying ACME and every failure pages IRIS's Telegram. So:

1. DNS `api` A record → `207.180.237.21`, grey (§6). Wait until
   `dig +short api.goldcoasttokota.store` returns that IP.
2. The tokota stack is up and `web` is healthy (§4).
3. Then, in a quiet window, from `/opt/iris`: `./deploy/deploy.sh`. Its
   closing `docker compose up -d caddy` recreates Caddy because the Compose
   config changed, which takes the edge down for 1–2 s for both apps.
4. `docker compose exec caddy caddy validate --config /etc/caddy/Caddyfile`,
   and watch `docker compose logs -f caddy` for
   "certificate obtained successfully".

The Caddyfile is a single-file bind mount and `git pull` replaces the file
rather than editing it, so a later Caddyfile-only change isn't seen by the
running Caddy until it's recreated:
`docker compose up -d --force-recreate caddy`. `caddy reload` won't pick it
up.

Health check: `https://api.goldcoasttokota.store/up` returns 200, and IRIS
still answers on its own domain.

**Testing from an office or campus network?** Some networks filter DNS
(Fortinet's "newly registered domain" category, for one) and answer for
`goldcoasttokota.store` with a block page's address (`208.91.112.55` on
FortiGuard). The record is fine if
`curl -s 'https://dns.google/resolve?name=api.goldcoasttokota.store&type=A'`
shows `207.180.237.21`. Test past the filter with
`curl --resolve api.goldcoasttokota.store:443:207.180.237.21 https://api.goldcoasttokota.store/up`
or from the box itself. Customers behind the same filters will see the block
too until the domain ages out of that category; it can be submitted for
recategorisation at fortiguard.com/webfilter.

## 6. Cloudflare

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
| `api` | `207.180.237.21` (A record) | **DNS only** (grey), the same as IRIS's `iris-api`. Caddy terminates TLS with its own ACME certificate |
| `@`, `www`, `admin` | Vercel, as Vercel's domain screen says | **DNS only** (grey). Vercel advises against proxying, which interferes with its SSL and caching |
| `media` | set up by R2 in step 2 | (managed by R2) |

Why grey and not proxied: the server's IP is already public through IRIS, so
proxying `api` would hide nothing, and it would add a Cloudflare Origin
Certificate to mount into Caddy plus a list of Cloudflare IP ranges to keep
current. If `api` is ever switched to proxied, set SSL/TLS to **Full
(strict)** (never Flexible) and add Cloudflare's ranges to Caddy's global
`servers { trusted_proxies static ... }`, or every customer shares one IP for
rate limiting.

**Paystack:** set the webhook URL in the Paystack dashboard to
`https://api.goldcoasttokota.store/api/v1/webhooks/paystack`.

## 7. Deploying

Every deploy after the first:

```bash
cd /opt/tokota && git pull --ff-only
cd backend
docker network inspect edge >/dev/null 2>&1 || docker network create edge
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml run --rm app \
  php artisan migrate --force --database=pgsql_direct
docker compose -f docker-compose.prod.yml up -d
docker image prune -f
```

`up -d` replaces the containers whose image changed. The worker gets
SIGTERM, finishes the job in hand (up to 60 s), and comes back on the new
code, so no `queue:restart` is needed. `web` is down for a second or two while
it's recreated. None of this touches IRIS or Caddy.

Migrations use Neon's **direct** connection (`neon.md` §2); everything else
uses the pooled one.

## 8. Keeping an eye on it

- Logs: `docker compose -f docker-compose.prod.yml logs -f app worker scheduler web`.
- Uptime: point a free uptime monitor at `/up`.
- Resources: the box has 8 GB of RAM shared with IRIS, so every Tokota
  container has a `mem_limit` (about 1.7 GB in total: app 768m, worker 384m,
  scheduler 256m, redis 256m, web 64m). A runaway PHP process is killed and
  restarted, not allowed to push IRIS into swap. Check
  `docker stats --no-stream` after launch.
- Backups: the database is backed up by Neon and the images by R2. Redis
  only holds the queue, cache and sessions. What lives only on this server is
  `.env`; keep a copy in the password manager.

`backend/Dockerfile` is the production image. It has nothing to do with local
development, which still runs `php artisan serve` (CLAUDE.md, Commands).

## Checklist

- [ ] IRIS owner agrees to the Caddy change and a quiet window
- [ ] `edge` network exists; repo cloned to `/opt/tokota`
- [ ] Images built
- [ ] `.env` filled in (group 82, mode 640), `APP_KEY` generated once and backed up
- [ ] `migrate:status` against Neon lists every migration as Ran
- [ ] Stack up; `web` healthy; `/up` answers inside it
- [ ] DNS `api` → `207.180.237.21`, grey, and resolving, **before** the Caddy change
- [ ] IRIS `deploy.sh` run in a quiet window (Caddy on `edge` + site block); `caddy validate` passes; cert obtained; IRIS still up
- [ ] `/up` returns 200 through `https://api.goldcoasttokota.store`
- [ ] R2 buckets, custom domain, token
- [ ] Paystack webhook URL updated
- [ ] Storefront and admin on Vercel have
      `NUXT_PUBLIC_API_BASE=https://api.goldcoasttokota.store/api/v1`
- [ ] Smoke test: admin sign-in, a product page, a test checkout
