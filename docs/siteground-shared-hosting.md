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

## Public repository

This repo may be public. Do **not** commit live SSH hostname, username, port, IP, or absolute filesystem paths. Put those only in:

- GitHub Environment **`production`** secrets
- `~/.ssh/config` (`Host ellr-timesheet-sg`)
- `.cursor/rules/siteground-ssh.local.mdc` (gitignored)

Rotate the deploy key if it was ever committed or pasted into a ticket.

## Cron (Shared)

SiteGround Shared may not expose `crontab` over SSH. Prefer **Site Tools → Devs → Cron Jobs** (or **Site → Cron Jobs**):

| Field | Value |
|-------|--------|
| Schedule | Every minute (`* * * * *`) |
| Command | `cd` into the Laravel app root (`SG_PATH_API`), then `/usr/local/php83/bin/php-cli artisan schedule:run` |

This runs reconcile (hourly), snapshot/failed-job prune, and the queue drain every minute.

## GitHub Actions deploy (no FTP)

SiteGround Shared has no GitOps agent. GitHub is the source of truth: [`.github/workflows/deploy-siteground.yml`](../.github/workflows/deploy-siteground.yml) builds the SPAs on the runner, rsyncs over SSH, then runs Composer + Artisan on the API host.

Do **not** build Node on Shared. Do **not** use FTP in CI.

Deploy is **manual only** (`workflow_dispatch`). The job uses the GitHub Environment **`production`** (required reviewer). A push to `main` does not deploy.

Always **Use workflow from `main`**. Pass `git_ref` when you want to deploy another tag or SHA (rollback). Do not start the run from the old tag: the environment is restricted to `main`, and you want the current workflow file with the older application commit. The job refuses commits that are not ancestors of `origin/main` (no random feature-branch deploys via `git_ref`).

Each successful run creates an annotated Git tag `deploy-YYYYMMDD-HHMMSS` (UTC) on the commit that was actually checked out. List recent production deploys with `git tag -l 'deploy-*' --sort=-creatordate`. The GitHub Environment timeline records the workflow branch (`main`), not the `git_ref` input; the tag is the source of truth for which code went live.

### 1. Enable SSH on SiteGround

1. Site Tools → **Devs** → **SSH Keys Manager** (wording may vary slightly).
2. Enable SSH for the account if it is off.
3. Note **SSH hostname**, **username**, and **port** (often `18765` on SiteGround Shared). Keep them out of git.

### 2. Create a deploy key (local machine)

```bash
ssh-keygen -t ed25519 -C "github-actions@timesheet.ellr.ca" -f ~/.ssh/ellr_timesheet_sg_deploy -N ""
```

- Add the **public** key (`*.pub`) in SiteGround SSH Keys Manager (or append to `~/.ssh/authorized_keys` over an existing SSH session).
- Keep the **private** key for GitHub Environment secrets only; never commit it.
- Point `Host ellr-timesheet-sg` in `~/.ssh/config` at this key (`IdentitiesOnly yes`).

Test from your machine:

```bash
ssh ellr-timesheet-sg "pwd && ls"
```

### 3. Resolve remote paths

SiteGround keeps each site’s web root as `public_html/` under `/home/customer/www/<public-hostname>/`. The Laravel app root is the **parent** of the API `public_html/` (secret `SG_PATH_API`). Admin and timesheet secrets point at those sites’ `public_html/` folders.

The deploy workflow syncs `backend/` beside the API `public_html/` and maps `backend/public/` → `public_html/` (Laravel `index.php` already uses `../` for `vendor` / `bootstrap`).

| Secret | Meaning |
|--------|---------|
| `SG_PATH_API` | Laravel backend root (parent of `public_html/`) |
| `SG_PATH_ADMIN` | Admin SPA document root |
| `SG_PATH_TIMESHEET` | Timesheet SPA document root |

### 4. GitHub Environment secrets

Repo → **Settings** → **Environments** → **`production`**. Store secrets on that environment (not in markdown):

| Secret | Value |
|--------|---------|
| `SG_SSH_HOST` | SiteGround SSH hostname from Site Tools |
| `SG_SSH_PORT` | SSH port from Site Tools |
| `SG_SSH_USER` | SSH username from Site Tools |
| `SG_SSH_PRIVATE_KEY` | Full private key from `~/.ssh/ellr_timesheet_sg_deploy` |
| `SG_PATH_API` | Laravel backend root (parent of API `public_html/`) |
| `SG_PATH_ADMIN` | Admin SPA document root |
| `SG_PATH_TIMESHEET` | Timesheet SPA document root |

The workflow hardcodes `VITE_API_URL=https://api.timesheet.ellr.ca/api` (no secret required for the API base URL).

Restrict the environment to the `main` branch and require a reviewer before the job runs.

### 5. First-time server bootstrap (before first Actions run)

On the API path only:

1. Ensure `backend/.env` exists on the server (production Shared baseline above). Never let CI overwrite it (workflow excludes `.env`).
2. `storage/` and `bootstrap/cache` must be writable by the PHP user.
3. Configure the minute cron above.

SiteGround Shared often defaults the account PHP CLI to 8.2. The workflow must call:

```bash
/usr/local/php83/bin/php-cli /usr/local/bin/composer.phar …
/usr/local/php83/bin/php-cli artisan …
```

Also set the **API site** PHP version to **8.3** in Site Tools (Devs / PHP Manager) so `public_html` requests match Composer.

### 6. Run the workflow

1. Merge to `main`, then **Actions** → **Deploy SiteGround** → **Run workflow**.
2. **Use workflow from `main`**. Leave `git_ref` empty. Leave **Run migrations** checked.
3. Approve the pending **production** deployment if a reviewer is required.
4. On success: confirm the new `deploy-*` tag in the job summary, then `curl -s https://api.timesheet.ellr.ca/api/health`.
5. Smoke login on both SPAs after DNS/SSL are ready.

### 7. Rollback

This restores **application code and SPA builds** by deploying a previous `deploy-*` tag. It does not undo MySQL migrations, data, or the server `.env`.

1. Pick the last known-good tag: `git tag -l 'deploy-*' --sort=-creatordate` (or the Tags page).
2. **Actions** → **Deploy SiteGround** → **Run workflow**.
3. **Use workflow from `main`**. Set `git_ref` to that tag (or a commit SHA).
4. Uncheck **Run migrations**.
5. Approve `production`. Smoke health + login on both SPAs.

If the bad deploy already applied a schema change, old PHP may not boot. Do not roll back the code until the schema is compatible (forward-fix, or a careful manual `migrate:rollback` only when you are sure it is safe).

A rollback run still creates a **new** `deploy-*` tag on the same commit, so history shows that SHA went live again.

### What the workflow does

1. Checkout `git_ref` if set, otherwise the commit of the selected workflow branch
2. `npm ci` + build admin and timesheet with production `VITE_API_URL`
3. `rsync` backend → `SG_PATH_API` (excludes `.env`, `vendor/`, `storage/`, tests)
4. `rsync` SPA `dist/` → admin and timesheet roots
5. SSH: `composer install --no-dev`, optional `migrate --force`, config/route/view cache, then purge SiteGround Dynamic Cache (SuperCacher) for `timesheet.ellr.ca`, `admin.timesheet.ellr.ca`, and `api.timesheet.ellr.ca` via local NGINX `PURGE`
6. On success: annotated tag `deploy-YYYYMMDD-HHMMSS` (UTC) on the deployed commit

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
   for host in timesheet.ellr.ca admin.timesheet.ellr.ca api.timesheet.ellr.ca; do
     curl -sS -X PURGE "http://127.0.0.1/*" -H "Host: ${host}"
   done
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
