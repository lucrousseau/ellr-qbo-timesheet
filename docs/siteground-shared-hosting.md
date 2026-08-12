# SiteGround Shared hosting

Temporary production path while the product runs on **SiteGround Shared**. Prefer Cloud/VPS within about a year; keep both modes available via `.env` so a later move does not require code changes.

For QBO webhook/reconcile details see [quickbooks-time-activity-sync.md](./quickbooks-time-activity-sync.md). Generic production checklist: [README Production](../README.md#production).

## Dual mode (config only)

| Mode | When | Queue | Cache | Drain flag |
|------|------|-------|-------|------------|
| **Shared** (SiteGround) | No Supervisor / no Redis | `database` | `database` | `QUEUE_SHARED_HOSTING_DRAIN=true` |
| **Cloud / VPS** | Dedicated `queue:work` | `redis` (or `database`) | `redis` (or `database`) | `QUEUE_SHARED_HOSTING_DRAIN=false` |

Shared drain: each `schedule:run` (every minute) runs `queue:work --stop-when-empty` so webhooks and approval sync still process, with up to ~1 minute latency. Do **not** enable drain on Cloud while a permanent worker runs (double processing).

## Host layout (Ellr Timesheet on Shared)

Same SiteGround account, three sites / document roots (never the monorepo root):

| Host | Document root on SiteGround |
|------|-----------------------------|
| `api.timesheet.ellr.ca` | `.../api.timesheet.ellr.ca/public_html` (= Laravel `public/`; app code is the parent folder) |
| `admin.timesheet.ellr.ca` | `.../admin.timesheet.ellr.ca/public_html` |
| `timesheet.ellr.ca` | `.../timesheet.ellr.ca/public_html` |

Requirements:

- PHP **8.3+** for the API site
- MySQL database
- HTTPS on all three hosts
- Never point a site at the monorepo root (would expose `.env` / `app/`)

SPA deep links (`/reset-password`) need Apache rewrite. Builds copy `apps/*/public/.htaccess` into each `dist/`.

Cookie / Sanctum parent for this product tree:

```env
SESSION_DOMAIN=.timesheet.ellr.ca
SANCTUM_STATEFUL_DOMAINS=admin.timesheet.ellr.ca,timesheet.ellr.ca
```

## Shared `.env` (API)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.timesheet.ellr.ca
LOG_LEVEL=info

ALLOW_REGISTRATION=false
REQUIRE_EMAIL_VERIFICATION=true

DB_CONNECTION=mysql
# … credentials …

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
SESSION_DOMAIN=.timesheet.ellr.ca

CACHE_STORE=database
QUEUE_CONNECTION=database
QUEUE_SHARED_HOSTING_DRAIN=true
QUEUE_SHARED_HOSTING_DRAIN_MAX_TIME=50
QUEUE_SHARED_HOSTING_DRAIN_TRIES=3

TRUSTED_PROXIES=*

QUICKBOOKS_CLIENT_ID=…
QUICKBOOKS_CLIENT_SECRET=…
QUICKBOOKS_REDIRECT_URI=https://api.timesheet.ellr.ca/api/quickbooks/callback
QUICKBOOKS_BASE_URL=production
QUICKBOOKS_EXPOSE_API_ERRORS=false
QUICKBOOKS_WEBHOOK_VERIFIER=…

FRONTEND_ADMIN_URL=https://admin.timesheet.ellr.ca
FRONTEND_TIMESHEET_URL=https://timesheet.ellr.ca
FRONTEND_AUTH_URL=https://timesheet.ellr.ca
SANCTUM_STATEFUL_DOMAINS=admin.timesheet.ellr.ca,timesheet.ellr.ca

MAIL_MAILER=smtp
# … SMTP …
```

## Cron (Shared)

One cron entry is enough when drain is enabled:

```cron
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

This runs reconcile (hourly), snapshot/failed-job prune, and the queue drain every minute.

## GitHub Actions deploy (no FTP)

SiteGround Shared has no GitOps agent. GitHub is the source of truth: [`.github/workflows/deploy-siteground.yml`](../.github/workflows/deploy-siteground.yml) builds the SPAs on the runner, rsyncs over SSH, then runs Composer + Artisan on the API host.

Do **not** build Node on Shared. Do **not** use FTP in CI.

### 1. Enable SSH on SiteGround

1. Site Tools → **Devs** → **SSH Keys Manager** (wording may vary slightly).
2. Enable SSH for the account if it is off.
3. Note **SSH hostname**, **username**, and **port** (often `18765` on SiteGround Shared).

### 2. Create a deploy key (local machine)

```bash
ssh-keygen -t ed25519 -C "github-actions@timesheet.ellr.ca" -f ~/.ssh/ellr_timesheet_sg_deploy -N ""
```

- Add the **public** key (`*.pub`) in SiteGround SSH Keys Manager (or append to `~/.ssh/authorized_keys` over an existing SSH session).
- Keep the **private** key for GitHub Secrets only; never commit it.

Test from your machine:

```bash
ssh -p 18765 YOUR_USER@YOUR_SSH_HOST "pwd && ls"
```

### 3. Resolve remote paths

Ellr Timesheet on this SiteGround account (confirmed layout):

```text
/home/customer/www/api.timesheet.ellr.ca/              ← Laravel app root (SG_PATH_API)
/home/customer/www/api.timesheet.ellr.ca/public_html/  ← Laravel public/ (web root)
/home/customer/www/admin.timesheet.ellr.ca/public_html/
/home/customer/www/timesheet.ellr.ca/public_html/
```

SiteGround keeps subdomain web roots as `public_html/`. The deploy workflow syncs `backend/` beside that folder and maps `backend/public/` → `public_html/` (Laravel `index.php` already uses `../` for `vendor` / `bootstrap`).

| Secret | Meaning |
|--------|---------|
| `SG_PATH_API` | Laravel backend root (parent of `public_html/`) |
| `SG_PATH_ADMIN` | Admin SPA document root |
| `SG_PATH_TIMESHEET` | Timesheet SPA document root |

### 4. GitHub repository secrets

Repo → **Settings** → **Secrets and variables** → **Actions**. Create:

| Secret | Value (this account) |
|--------|----------------------|
| `SG_SSH_HOST` | `giowm1268.siteground.biz` |
| `SG_SSH_PORT` | `18765` |
| `SG_SSH_USER` | `u3261-ghor3kynhvht` |
| `SG_SSH_PRIVATE_KEY` | Full private key from `~/.ssh/ellr_timesheet_sg_deploy` |
| `SG_PATH_API` | `/home/customer/www/api.timesheet.ellr.ca` |
| `SG_PATH_ADMIN` | `/home/customer/www/admin.timesheet.ellr.ca/public_html` |
| `SG_PATH_TIMESHEET` | `/home/customer/www/timesheet.ellr.ca/public_html` |

The workflow hardcodes `VITE_API_URL=https://api.timesheet.ellr.ca/api` (no secret required for the API base URL).

### 5. First-time server bootstrap (before first Actions run)

On the API path only:

1. Ensure `backend/.env` exists on the server (production Shared baseline above). Never let CI overwrite it (workflow excludes `.env`).
2. `storage/` and `bootstrap/cache` must be writable by the PHP user.
3. Configure the minute cron above.
4. Optional dry check: `php -v` (8.3+) and `composer -V` over SSH.

### 6. Run the workflow

1. Merge the workflow to `main`, or use **Actions** → **Deploy SiteGround** → **Run workflow**.
2. On success: `curl -s https://api.timesheet.ellr.ca/api/health`
3. Smoke login on both SPAs after DNS/SSL are ready.

Push to `main` also triggers deploy. Use `workflow_dispatch` for the first controlled run after secrets are set.

### What the workflow does

1. `npm ci` + build admin and timesheet with production `VITE_API_URL`
2. `rsync` backend → `SG_PATH_API` (excludes `.env`, `vendor/`, `storage/`, tests)
3. `rsync` SPA `dist/` → admin and timesheet roots
4. SSH: `composer install --no-dev`, `migrate --force`, config/route/view cache

## Manual deploy steps (fallback)

1. Build frontends **off the server** (Node 22+):

   ```bash
   VITE_API_URL=https://api.timesheet.ellr.ca/api
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
8. Smoke: `curl -s https://api.timesheet.ellr.ca/api/health`, login on both SPAs, connect QBO, approve one entry and wait up to a minute for the drain.

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
