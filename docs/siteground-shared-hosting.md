# SiteGround Shared hosting

Temporary production path while the product runs on **SiteGround Shared**. Prefer Cloud/VPS within about a year; keep both modes available via `.env` so a later move does not require code changes.

For QBO webhook/reconcile details see [quickbooks-time-activity-sync.md](./quickbooks-time-activity-sync.md). Generic production checklist: [README Production](../README.md#production).

## Dual mode (config only)

| Mode | When | Queue | Cache | Drain flag |
|------|------|-------|-------|------------|
| **Shared** (SiteGround) | No Supervisor / no Redis | `database` | `database` | `QUEUE_SHARED_HOSTING_DRAIN=true` |
| **Cloud / VPS** | Dedicated `queue:work` | `redis` (or `database`) | `redis` (or `database`) | `QUEUE_SHARED_HOSTING_DRAIN=false` |

Shared drain: each `schedule:run` (every minute) runs `queue:work --stop-when-empty` so webhooks and approval sync still process, with up to ~1 minute latency. Do **not** enable drain on Cloud while a permanent worker runs (double processing).

## Host layout (same parent domain)

| Host | Document root |
|------|----------------|
| `api.yourdomain.com` | `backend/public` |
| `admin.yourdomain.com` | `apps/admin/dist` (after Vite build) |
| `timesheet.yourdomain.com` | `apps/timesheet/dist` (after Vite build) |

Requirements:

- PHP **8.3+** for the API site
- MySQL database
- HTTPS on all three hosts
- Never point a site at the monorepo root (would expose `.env` / `app/`)

SPA deep links (`/reset-password`) need Apache rewrite. Builds copy `apps/*/public/.htaccess` into each `dist/`.

## Shared `.env` (API)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
LOG_LEVEL=info

ALLOW_REGISTRATION=false
REQUIRE_EMAIL_VERIFICATION=true

DB_CONNECTION=mysql
# … credentials …

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
SESSION_DOMAIN=.yourdomain.com

CACHE_STORE=database
QUEUE_CONNECTION=database
QUEUE_SHARED_HOSTING_DRAIN=true
QUEUE_SHARED_HOSTING_DRAIN_MAX_TIME=50
QUEUE_SHARED_HOSTING_DRAIN_TRIES=3

TRUSTED_PROXIES=*

QUICKBOOKS_CLIENT_ID=…
QUICKBOOKS_CLIENT_SECRET=…
QUICKBOOKS_REDIRECT_URI=https://api.yourdomain.com/api/quickbooks/callback
QUICKBOOKS_BASE_URL=production
QUICKBOOKS_EXPOSE_API_ERRORS=false
QUICKBOOKS_WEBHOOK_VERIFIER=…

FRONTEND_ADMIN_URL=https://admin.yourdomain.com
FRONTEND_TIMESHEET_URL=https://timesheet.yourdomain.com
FRONTEND_AUTH_URL=https://timesheet.yourdomain.com
SANCTUM_STATEFUL_DOMAINS=admin.yourdomain.com,timesheet.yourdomain.com

MAIL_MAILER=smtp
# … SMTP …
```

## Cron (Shared)

One cron entry is enough when drain is enabled:

```cron
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

This runs reconcile (hourly), snapshot/failed-job prune, and the queue drain every minute.

## Deploy steps (Shared)

1. Build frontends **off the server** (Node 22+):

   ```bash
   # apps/admin/.env.production and apps/timesheet/.env.production
   VITE_API_URL=https://api.yourdomain.com/api

   npm run build:admin
   npm run build:timesheet
   ```

2. Upload API code; set document root to `backend/public`.
3. Upload `apps/admin/dist` and `apps/timesheet/dist` to the two SPA document roots (include `.htaccess`).
4. On the API host:

   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

5. Ensure `storage/` and `bootstrap/cache` are writable.
6. Configure the cron above.
7. Intuit portal: production redirect URI + webhook URL (see sync doc).
8. Smoke: `curl -s https://api.yourdomain.com/api/health`, login on both SPAs, connect QBO, approve one entry and wait up to a minute for the drain.

## Moving to Cloud / VPS later

1. Provision Redis (optional) and a Supervisor (or equivalent) `queue:work` process.
2. Set `QUEUE_SHARED_HOSTING_DRAIN=false`.
3. Optionally switch `CACHE_STORE` / `QUEUE_CONNECTION` to `redis`.
4. Keep `TRUSTED_PROXIES=*` if still behind a proxy.
5. `php artisan config:cache` and restart the worker.

No application code change required for that switch.

## Shared limitations (accepted)

- Queue latency up to about one minute (cron drain).
- No Redis: use database cache/queue; fine for a single Shared instance.
- SiteGround process limits may kill a long drain; keep `QUEUE_SHARED_HOSTING_DRAIN_MAX_TIME` under 55 seconds.
- Prefer SiteGround Cloud when budget allows for a permanent worker and lower sync latency.
