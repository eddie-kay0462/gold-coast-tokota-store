# Database on Neon

The production database is Postgres on [Neon](https://neon.tech). The API runs
on a Contabo server (see `contabo.md`) and talks to Neon over the internet,
always over SSL.

Owner: the backend developer. The server side is in `contabo.md`.

---

## 1. Create the project

In the Neon console, **New project**:

| Setting | Value | Why |
|---|---|---|
| Name | `gold-coast-tokota` | |
| Postgres version | **18** | Neon's default, and what the production project runs (18.6). The test suite passes on it, and CI runs it against 18 on every PR. Local Postgres 16 still works for development |
| Region | **AWS Europe Central 1 (Frankfurt)** | Closest to Contabo's German data centres, so each query is a few ms. A far-away region adds that delay to *every* query |
| Database name | `gold_coast_tokota` | Matches `.env.example` |

Neon creates a role (user) with the project. Keep its password somewhere safe
(a password manager, not chat or email).

Neon calls the resulting database the `main` **branch**. Production lives there.

## 2. Get the two connection strings

**Dashboard → Connect.** Neon shows a connection string with a
**Connection pooling** toggle. You need both versions:

| Toggle | Hostname contains | Goes into | Used for |
|---|---|---|---|
| **On** (pooled) | `-pooler` | `DB_URL` | The API, the queue worker, the scheduler |
| **Off** (direct) | no `-pooler` | `DB_DIRECT_URL` | `php artisan migrate` only |

Both look like:

```
postgresql://<role>:<password>@ep-xxxx-pooler.eu-central-1.aws.neon.tech/gold_coast_tokota?sslmode=require
```

Laravel reads them as they are, `postgresql://` scheme included.

**Why two:** the pooler lets many PHP processes share a few real database
connections. That's right for the app. Migrations are better run on a plain
connection, which is what Neon recommends. `config/database.php` has a
`pgsql_direct` connection for exactly this:

```bash
php artisan migrate --force --database=pgsql_direct
```

Everything else uses the default `pgsql` connection (pooled).

The app is pooler-safe: its row locks (`lockForUpdate` in stock reservations
and bookings) run inside transactions, and nothing uses session-level
features (`LISTEN`, advisory locks) that a pooler breaks.

## 3. The values to hand over

These go in the server's `backend/.env` (the colleague setting up Contabo
needs them). Send them through a password manager, not chat:

```dotenv
DB_CONNECTION=pgsql
DB_URL=postgresql://...-pooler.eu-central-1.aws.neon.tech/gold_coast_tokota?sslmode=require
DB_DIRECT_URL=postgresql://....eu-central-1.aws.neon.tech/gold_coast_tokota?sslmode=require
DB_SSLMODE=require
```

`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` are not
needed in production: `DB_URL` overrides them.

## 4. Try it from your machine first

Before the server exists, point a local checkout at Neon to prove the
connection, then put your local settings back:

```bash
cd backend
DB_URL='<pooled string>' DB_DIRECT_URL='<direct string>' DB_SSLMODE=require \
  php artisan migrate --force --database=pgsql_direct

DB_URL='<pooled string>' DB_SSLMODE=require php artisan migrate:status
```

Values passed on the command line beat `.env`, so nothing local is changed.
Make sure `php artisan config:clear` has been run first. A cached config
ignores them.

## 5. First data

**Is there real data on the old Render database** (orders, customers, real
admin accounts)?

- **No** — seed the catalogue and the first admin on Neon:

  ```bash
  APP_ENV=production SEED_ADMIN_PASSWORD='<12+ character password>' \
  DB_URL='<pooled string>' DB_SSLMODE=require \
    php artisan db:seed --force
  ```

  `APP_ENV=production` matters when this runs from a laptop: without it the
  seeder sees your local environment and uses the development password.

  This creates `admin@goldcoasttokota.store` as `super_admin` with that
  password. In production the seeder **refuses to run** without
  `SEED_ADMIN_PASSWORD`, because its development password is `password`.
  Sign in and create the real team accounts, then remove or demote the seeded
  one.

- **Yes** — copy it over. Stop the old API first so nothing is written
  mid-copy:

  ```bash
  pg_dump --format=custom --no-owner --no-acl '<render external URL>' > tokota.dump
  pg_restore --no-owner --no-acl --dbname='<Neon DIRECT string>' tokota.dump
  ```

  Use the **direct** string for the restore. Then check a few row counts
  (`select count(*) from orders;` on both) before switching the API over.
  Delete `tokota.dump` afterwards — it holds customer data.

## 6. Settings worth knowing

- **Idle sleep (scale to zero).** Neon suspends the database after a few
  minutes without queries and wakes it on the next one (a short delay, under
  a second). With the queue, cache and sessions in Redis on the server
  (`contabo.md`), the database only gets queries from real traffic and
  hourly/5-minute scheduled jobs, so it can actually sleep. **If those were
  left on `database`, the worker would poll Neon every 3 seconds and keep it
  awake around the clock**, which eats the free plan's compute hours.
- **Plan.** Check the current free-plan limits (compute hours, storage, how
  far back you can restore) against expected use before launch. A shop taking
  real orders should be on a paid plan for the longer restore window.
- **Restore.** Neon keeps history and can restore the database to an earlier
  point in time. Know where that button is before you need it.
- **Branches.** A branch is a full copy of the database made in seconds.
  Before a risky migration, branch `main`, run the migration against the
  branch's direct string, and look at the result. Branches can also back a
  staging API.
- **IP allow list** (paid plans): once the Contabo server has a fixed IP,
  restrict the database to it.

## Checklist

- [ ] Project in Frankfurt, Postgres 18, database `gold_coast_tokota`
- [ ] Pooled and direct strings saved in the password manager
- [ ] Migrations run from a laptop via `--database=pgsql_direct`
- [ ] Data seeded with `SEED_ADMIN_PASSWORD`, or copied from Render
- [ ] Strings handed to whoever sets up the server
- [ ] Plan limits checked; restore window known
