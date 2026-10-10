# Gold Coast Tokota

E-commerce + booking platform for a Ghana sandals brand.
Full spec: see README.md — read it before starting any feature.

## Stack
Nuxt 3 (storefront, hybrid SSR/SPA) · Nuxt 3 (admin dashboard, SPA-only, separate app) · Laravel 12 API · PostgreSQL (Render) · Tailwind · GSAP

> **Deviation from README:** README/original spec says Laravel 11 and puts
> the admin dashboard at `/admin/**` inside the one storefront Nuxt app. Two
> changes were made during scaffolding:
> 1. **Laravel 12, not 11** — the entire Laravel 11 branch is flagged with
>    unpatched security advisories in Composer (Laravel ships up to v13.x
>    now). Same architecture (Sanctum SPA cookie auth, Eloquent, Form
>    Requests, API Resources); no README implementation detail assumes
>    anything 11-specific.
> 2. **Admin is a separate top-level app (`admin/`), not `frontend/pages/admin/**`**
>    — three deployables total: `frontend/` (storefront), `admin/` (dashboard),
>    `backend/` (API). Requested explicitly so admin has its own URL
>    (`admin.goldcoasttokota.store`), own build/deploy cycle, and no shared
>    bundle with the public storefront. Both frontend apps are plain Nuxt 3
>    talking to the same Laravel API; `admin/` has no SSR requirement (whole
>    app is `routeRules: { '/**': { ssr: false } }`) since it's entirely
>    behind Sanctum-authenticated Admin/Staff login. (Note: a top-level
>    `ssr: false` config crashes the dev server in this Nuxt/Vite version
>    combo with "No entry found in rollupOptions.input" — use the routeRules
>    form instead, not the shorthand.)

## Key architectural rules
- SSR routes (frontend/): /, /about, /shop/**, /blog/** — GSAP must be client-only (`onMounted`/`<ClientOnly>`), never touch `window`/`document` during SSR
- SPA routes (frontend/): /checkout, /order-confirmation/**
- admin/ is SPA-only in its entirety — no SSR anywhere in that app
- **Four role tiers, enforced by capability, not by tier.** `super_admin` /
  `admin` / `staff` / `intern`. Routes carry `capability:<name>` (comma-separate
  several; all are required), checked against `App\Support\AdminCapability` on
  the `admin` Sanctum guard, never the `web` (Customer) guard. The older
  `EnsureAdminRole`/`EnsureStaffOrAdminRole` pair is gone: it could only ask
  "admin or not", and `GOLD_COAST_TOKOTA.md` §18 does not divide that way — an
  Admin may change prices and issue refunds but **not** touch system settings or
  payment credentials, while Staff may adjust stock but not price it.
  `AdminCapability` is the server-side twin of `admin/utils/permissions.ts`;
  **change both or neither**
- USD is always derived from GHS × FxRate, never a static field; lock the rate at checkout
- **Paystack is the only payment gateway**, for both GHS and USD.
  `GOLD_COAST_TOKOTA.md` §13/§22.12 name it and never mention Stripe, so
  `PaymentGatewayFactory` has no currency split and `client_secret` is always
  null. The Stripe config binding is retained but unrouted — see FOR_THE_TEAM.md
- **`GOLD_COAST_TOKOTA.md` (repo root) is the source of truth for business and
  brand rules** — shipping and returns windows, workshop schedules and
  capacities, production turnaround, payment methods, role tiers, currency. Its
  §22 is explicit: do not invent a policy it does not state, and do not change
  one it does. Where it and the README disagree, it wins, and the deviation gets
  written down in FOR_THE_TEAM.md
- Two separate identity models/guards: `Customer` (`web` guard, storefront/guest checkout) and `AdminUser` (`admin` guard, dashboard). No generic Laravel `User` model — it was removed during scaffolding since the spec has no use for it.
- Backend CORS (`config/cors.php`) allows two origins via `FRONTEND_URLS` (comma-separated): the storefront and the admin app. Sanctum's stateful domains (`SANCTUM_STATEFUL_DOMAINS`) must list both hosts too, or cookie-based admin login will silently fail CSRF/session checks.

## Commands
- Storefront dev: `cd frontend && npm run dev` (port 3000)
- Admin dev: `cd admin && npm run dev` (port 3001)
- Backend dev: `cd backend && php artisan serve` (port 8000)
- Backend migrate: `cd backend && php artisan migrate`
- Backend seed (creates a test AdminUser, default SiteSetting row, About page): `cd backend && php artisan db:seed`
- Both frontend apps need `NUXT_PUBLIC_API_BASE` pointed at the backend's `/api/v1` (defaults to `http://localhost:8000/api/v1`)

## Current phase
See "Implementation Order" in README.md — work sequentially, don't skip ahead.

## Team status log
`FOR_THE_TEAM.md` (repo root) is the running status log for teammates: what
changed recently, what is still outstanding, and which decisions are waiting on
a human. **Update it in the same change that alters the code**, not afterwards —
bump the "Last updated" line, add a dated entry to "Recent changes", move
finished items out of "What is left to do", and record anything blocked. Its
closing section spells out the routine.

## Git commits and pull requests
- **No AI authorship or attribution, anywhere.** Claude, Cursor and every other
  AI coding agent/assistant stay out of commit authors and committers, out of
  `Co-Authored-By` (or any other) trailers, and out of PR descriptions: no
  "Generated with …" footers or session links. Commit as the human you are
  working for (their own git identity; for eddie-kay0462 that is
  `Edward Ofosu Mensah <135532748+eddie-kay0462@users.noreply.github.com>`),
  never as `Claude <noreply@anthropic.com>`. Check `git config user.name` before
  the first commit.
- **PRs target `dev`, never `main` directly.** Work merges into `dev` first;
  `dev` is merged into `main` separately.
