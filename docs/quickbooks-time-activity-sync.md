# QuickBooks time activity sync (Phase 2)

Two procedures: **local** (Docker) and **production**. Use **local with webhooks** when testing create/update/**delete** sync from QBO.

**Scope:** time activity **list** only. Writes still go to QuickBooks Online (QBO); the database (`time_activity_snapshots`) is a read model.

---

## Quick reference

| | Local (no webhook) | Local (webhooks + ngrok) | Production |
|---|-------------------|--------------------------|------------|
| Webhook | Off | **On** (tunnel) | **On** |
| `QUICKBOOKS_WEBHOOK_VERIFIER` | Empty | From Intuit Dev portal | From Intuit Prod portal |
| Intuit Webhooks URL | Empty | `https://<ngrok>/api/quickbooks/webhook` | `https://<api>/api/quickbooks/webhook` |
| QBO delete sync | Reconcile / hourly job (~minutes) | **Yes** (~seconds via webhook) | **Yes** (~seconds) |
| QBO create/update outside app | Reconcile / `?refresh=1` | Webhook + reconcile backup | Webhook + reconcile backup |
| Queue worker | `queue` service (auto with `docker compose up`) | Same | Supervisor / platform worker |
| Scheduled reconcile | `scheduler` service (auto with `docker compose up`) | Same | Cron `schedule:run` every minute |

### How sync works

| Trigger | What happens |
|---------|----------------|
| First list or empty realm | Queues `ReconcileRealmTimeActivitiesJob` (full lookback); returns DB snapshots immediately (often empty until the job finishes) |
| `GET /api/time-activities?refresh=1` | Full inline reconcile from QBO, then read from DB |
| Create / update / delete in this app | QBO write, then snapshot update |
| OAuth connect (admin) | Backfill job queued |
| Intuit webhook (prod) | Job queued, sync one TimeActivity |
| Scheduled reconcile | `quickbooks:reconcile-time-activities --scheduled` (default: hourly); recent window only, skips recently synced realms |
| Manual reconcile | `quickbooks:reconcile-time-activities` (no flags); full lookback for every realm |

Webhook handler only processes **TimeActivity** (`Create`, `Update`, `Delete`, `Void`).

---

## Procedure: Local (Docker)

Use this for UI work that does not depend on QBO pushing changes. **Skips webhook setup.**

For **delete** or full sync testing from QBO, use **Procedure: Local with webhooks** below instead.

### 1. Environment (`backend/.env`)

```env
QUICKBOOKS_WEBHOOK_VERIFIER=
QUICKBOOKS_TIME_ACTIVITIES_RECONCILE_ENABLED=true
QUEUE_CONNECTION=database
```

Leave the webhook verifier **empty**. Other `QUICKBOOKS_*` vars follow `backend/.env.example`.

### 2. Intuit Developer portal

- App > **Webhooks** > **Development**
- **Endpoint URL:** leave **empty** (do not save localhost)
- No verifier token needed

### 3. Start the stack (repo root)

```bash
docker compose up -d
```

After `.env` changes:

```bash
npm run sync:restart
```

### 4. Migrations (once, or after deploy)

```bash
npm run sync:migrate
```

`Nothing to migrate.` = OK (tables already exist).

### 5. Queue worker and scheduler

The **`queue`** service starts with `docker compose up` and runs `php artisan queue:work` in the background.

The **`scheduler`** service starts with `docker compose up` and runs `php artisan schedule:work` (hourly reconcile by default).

```bash
npm run sync:queue:logs
npm run sync:schedule:logs
```

Expected queue output: `Processing jobs from the [default] queue.`

Do **not** also run `php artisan queue:work` or `npm run sync:schedule` manually while the Docker services are up; duplicate workers can race on the same jobs or schedules.

Needed for OAuth backfill after admin connects QBO (queue) and periodic reconcile when webhooks are off (scheduler).

### 6. Connect QBO

1. Open admin: `http://localhost:5173`
2. Connect QuickBooks (OAuth)
3. Worker processes the backfill job automatically

### 7. Refresh when QBO changed outside the app

Choose one:

- Reload list with **`?refresh=1`** in the API (if the UI exposes it), or
- Manual reconcile:

  ```bash
  npm run sync:reconcile
  ```

  Success example: `Reconciled 20 time activity snapshot(s).`

### 8. Health check

```bash
curl -s http://localhost:8000/api/health
```

### Local command cheat sheet

```bash
npm run docker:up
npm run sync:restart
npm run sync:migrate
npm run sync:queue:logs
npm run sync:schedule:logs
npm run sync:reconcile
npm run dev:webhook-tunnel        # optional: Intuit webhooks via ngrok (near real-time sync)
npm run dev:webhook-tunnel:status # check ngrok tunnel, Docker workers, and Intuit endpoint URL
```

`npm run sync:schedule` is only needed when running the API **without** Docker (falls back to `backend/`).

`npm run artisan -- <command>` runs any Artisan command in Docker (`api`) when the stack is up, otherwise in `backend/`.

### Optional: local without Docker

If you use `npm run dev:api` instead of Docker, the `sync:*` and `artisan` scripts still work (they fall back to `backend/`). Start `npm run dev:admin` / `npm run dev:timesheet` separately.

---

## Procedure: Local with webhooks (sync + delete testing)

**Use this when developing Phase 2 sync.** Webhooks remove entries within seconds; reconcile (manual or hourly) also purges ghosts in the lookback window when QuickBooks no longer returns them and the reconcile scan was not truncated by `QUICKBOOKS_TIME_ACTIVITIES_SCAN_MAX_PAGES`.

You need **2 terminals**: Docker stack (includes queue worker and scheduler), ngrok tunnel.

### 1. Install ngrok (once per machine)

```bash
brew install ngrok/ngrok/ngrok
```

Sign up at [ngrok dashboard](https://dashboard.ngrok.com), then:

```bash
ngrok config add-authtoken <your-token>
```

### 2. Start Docker

**Terminal A** (repo root):

```bash
npm run docker:up
npm run sync:migrate
npm run sync:queue:logs
```

### 3. Start the tunnel

**Terminal B** (repo root):

```bash
npm run dev:webhook-tunnel
```

Copy the **https** URL shown by ngrok (Forwarding line), e.g. `https://abc123.ngrok-free.app`.

### 4. Intuit Developer portal (Development)

1. [developer.intuit.com](https://developer.intuit.com/) > your app > **Webhooks**
2. Environment: **Development**
3. **Endpoint URL:**

   ```text
   https://<ngrok-host>/api/quickbooks/webhook
   ```

   Example: `https://abc123.ngrok-free.app/api/quickbooks/webhook`

4. Toggle **Show verifier token**, copy the value
5. **Subscribed events:** **TimeActivity** only (uncheck all other entities)
6. Operations: **Create**, **Update**, **Delete**
7. **Enable cloud event payload format:** **Off**
8. **Save**

### 5. Update `backend/.env`

```env
QUICKBOOKS_WEBHOOK_VERIFIER=<paste-intuit-verifier-token>
```

Restart API and queue:

```bash
npm run sync:restart
```

### 6. Test the flow

1. Open timesheet/admin, confirm list loads
2. In **QuickBooks Online**, create or edit a time activity for your test employee
3. Within **~5–30 seconds**, refresh the list in the app (webhook → queue → snapshot)
4. **Delete** the entry in QBO → it should **disappear** from the app after refresh (webhook soft-deletes the snapshot)

### 7. If nothing happens

| Check | Command / action |
|-------|------------------|
| API up | `curl -s http://localhost:8000/api/health` |
| Worker running | `npm run sync:queue:logs` shows job processing |
| ngrok URL still valid | Restart ngrok = new URL → update Intuit |
| Verifier matches | `QUICKBOOKS_WEBHOOK_VERIFIER` = Intuit portal token |
| API restarted after `.env` | `npm run sync:restart` |
| Logs | `docker compose logs api --tail=50` |

### ngrok notes

- Free plan: URL changes every time you restart ngrok → update Intuit Webhooks URL
- Paid plan: fixed subdomain available
- ngrok must point at **host port 8000** (Docker publishes `127.0.0.1:8000`)

---

## Procedure: Production

Use this when deploying the API to a public HTTPS host.

### 1. Environment (secrets / `backend/.env`)

```env
APP_URL=https://api.yourdomain.com
APP_ENV=production
APP_DEBUG=false

QUICKBOOKS_REDIRECT_URI=https://api.yourdomain.com/api/quickbooks/callback
QUICKBOOKS_WEBHOOK_VERIFIER=<from-intuit-portal>
QUICKBOOKS_TIME_ACTIVITIES_RECONCILE_ENABLED=true
QUICKBOOKS_TIME_ACTIVITIES_RECONCILE_CRON="0 * * * *"

QUEUE_CONNECTION=database
# or redis in multi-instance setups
# SiteGround Shared: also set QUEUE_SHARED_HOSTING_DRAIN=true (see docs/siteground-shared-hosting.md)
# Cloud/VPS with Supervisor: leave QUEUE_SHARED_HOSTING_DRAIN=false
TRUSTED_PROXIES=*
```

### 2. Deploy and migrate

```bash
php artisan migrate --force
```

Run on deploy (CI/CD or SSH on the server).

### 3. Queue processing (choose one)

**Cloud / VPS (preferred):** permanent worker via Supervisor:

```ini
[program:ellr-queue]
command=php /path/to/backend/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
```

Set `QUEUE_SHARED_HOSTING_DRAIN=false` (default).

**SiteGround Shared:** no Supervisor. Set `QUEUE_SHARED_HOSTING_DRAIN=true` and rely on the minute cron `schedule:run` to drain with `queue:work --stop-when-empty` (up to ~1 minute latency). Full runbook: [`docs/siteground-shared-hosting.md`](./siteground-shared-hosting.md).

Or your platform equivalent (Railway worker, Forge daemon, etc.).

### 4. Scheduler (cron every minute)

```cron
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

Runs `quickbooks:reconcile-time-activities` hourly by default (backup if a webhook is missed). On Shared with drain enabled, the same cron also processes queued jobs.
### 5. Intuit Developer portal (Production)

1. App > **Webhooks** > switch to **Production** (or production app)
2. **Endpoint URL:**

   ```text
   https://api.yourdomain.com/api/quickbooks/webhook
   ```

3. **Show verifier token** → copy to `QUICKBOOKS_WEBHOOK_VERIFIER`
4. **Subscribed events:** **TimeActivity** only (uncheck everything else)
5. Operations: **Create**, **Update**, **Delete**
6. **Enable cloud event payload format:** **Off**
7. **Save**

### 6. OAuth (same app, separate setting)

In Intuit OAuth settings and `.env`:

```text
https://api.yourdomain.com/api/quickbooks/callback
```

Must match `QUICKBOOKS_REDIRECT_URI`.

### 7. Verify after deploy

1. `curl -s https://api.yourdomain.com/api/health`
2. Admin connects QBO (backfill job)
3. Change a time entry in QBO UI → list updates within seconds (webhook)
4. If stale: `php artisan quickbooks:reconcile-time-activities` on the server

### Production command cheat sheet

```bash
php artisan migrate --force
php artisan queue:work --sleep=3 --tries=3
php artisan quickbooks:reconcile-time-activities
php artisan schedule:run
```

---

## Shared environment variables

Full list in `backend/.env.example`:

| Variable | Local | Production |
|----------|-------|------------|
| `QUICKBOOKS_WEBHOOK_VERIFIER` | Empty | Intuit verifier token (secret) |
| `QUICKBOOKS_WEBHOOK_MAX_PAYLOAD_BYTES` | `262144` | Max signed webhook body size |
| `QUICKBOOKS_WEBHOOK_MAX_NOTIFICATIONS` | `50` | Max `eventNotifications` per payload |
| `QUICKBOOKS_WEBHOOK_MAX_ENTITIES_PER_NOTIFICATION` | `100` | Max entities per notification |
| `QUICKBOOKS_EXPOSE_API_ERRORS` | `false` | Keep `false` in production |
| `QUICKBOOKS_TIME_ACTIVITIES_RECONCILE_ENABLED` | `true` | `true` |
| `QUICKBOOKS_TIME_ACTIVITIES_RECONCILE_CRON` | `"0 * * * *"` | `"0 * * * *"` |
| `QUICKBOOKS_TIME_ACTIVITIES_LOOKBACK_DAYS` | `90` | `90` (tune if needed) |
| `QUEUE_CONNECTION` | `database` | `database` or `redis` |

---

## Security notes

- **`QUICKBOOKS_WEBHOOK_VERIFIER`** is a shared app secret: anyone with the verifier and a `realmId` can forge signed webhooks. Store it like a password; rotate via the Intuit Developer portal if leaked.
- Webhook ingress rejects oversized payloads and excessive `eventNotifications` / entity counts **before** queueing (see `QUICKBOOKS_WEBHOOK_MAX_*` in `.env.example`).
- Reconcile purge runs only when the smallest lookback scan completes fully **and** QuickBooks returned at least one activity id. An empty QBO window never mass-deletes local snapshots. Purge ids come from the smallest lookback window only (not the wider 30/90-day import passes).
- Webhook ingress checks payload size **before** HMAC verification to limit CPU spent on oversized unsigned bodies.
- Keep **`QUICKBOOKS_EXPOSE_API_ERRORS=false`** in production so Intuit error bodies are not returned to clients or written to webhook job logs.

---

## Troubleshooting

| Symptom | Local fix | Production fix |
|---------|-----------|----------------|
| List empty after connect | `docker compose logs queue`; then reconcile | Check worker logs; run reconcile |
| QBO change not in app (no webhook) | Run reconcile or `?refresh=1` | Fix ngrok URL / verifier / worker |
| QBO **delete** still visible | `npm run sync:reconcile` or wait for hourly reconcile | Fix webhooks / verifier / worker |
| Brief login error during sync | SQLite lock (api + queue + scheduler); restart stack after pull; WAL enabled in config | N/A |
| Webhook `401` | N/A (no webhook locally) | Match `QUICKBOOKS_WEBHOOK_VERIFIER` to Intuit |
| Jobs never run | `docker compose ps queue`; `docker compose up -d queue` | Start Supervisor / platform worker |
| Duplicate queue workers | Stop manual `queue:work` if `queue` service is running | One worker process per queue |
| Hourly sync missing | `docker compose ps scheduler`; `npm run sync:schedule:logs` | Add cron `schedule:run` every minute |
| Webhook tunnel health | `npm run dev:webhook-tunnel:status` | N/A |
| `ngrok: command not found` | Skip webhooks; use reconcile | N/A |

Logs: `backend/storage/logs/laravel.log`, `docker compose logs api`, `docker compose logs queue`, or `docker compose logs scheduler`.

---

## Related code

| Piece | Location |
|-------|----------|
| Webhook route | `backend/routes/api.php` |
| Webhook controller | `backend/app/Http/Controllers/Api/QuickBooksWebhookController.php` |
| Processor | `backend/app/Services/QuickBooksWebhookProcessorService.php` |
| Reconcile command | `backend/app/Console/Commands/ReconcileTimeActivitiesCommand.php` |
| Schedule | `backend/bootstrap/app.php` |
| Config | `backend/config/quickbooks.php` |
