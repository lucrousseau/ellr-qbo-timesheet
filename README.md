# Ellr QBO Timesheet

Minimal QuickBooks Online timesheet. Monorepo with a Laravel API, shared HTTP client, and two React apps.

**Project language: English** (UI copy, API messages, code comments, and agent rules).

## Architecture

```
┌─────────────────┐     ┌─────────────────┐
│  apps/admin     │     │ apps/timesheet  │
│  (React + Vite) │     │ (React + Vite)  │
└────────┬────────┘     └────────┬────────┘
         │                       │
         └───────────┬───────────┘
                     │ @ellr/api-client
                     │ REST API (Sanctum)
              ┌──────▼──────┐
              │   backend   │
              │  (Laravel)  │
              └──────┬──────┘
                     │ QBO SDK
              ┌──────▼──────┐
              │ QuickBooks  │
              │   Online    │
              └─────────────┘
```

Frontends talk only to the Laravel API via `@ellr/api-client`. Only the backend talks to QuickBooks Online through the official SDK.

## Stack (July 2026)

| Component | Version |
|-----------|---------|
| PHP | 8.3+ |
| Laravel | 13.22 |
| Laravel Sanctum | 4.3 |
| QuickBooks PHP SDK | 6.3.1 (`quickbooks/v3-php-sdk`) |
| React | 19.2 |
| Vite | 8.1 |
| Tailwind CSS | 4.3 |
| TypeScript | 6.0 |
| Pest | 4 (tests + mutation) |
| Stryker | 9.6 (frontend mutation) |

## Prerequisites

- PHP 8.3+ (`backend/.php-version` for version managers) or **Docker Desktop** (see [Docker development](#docker-recommended-for-portability))
- Composer 2.x (not required when using Docker)
- Node.js 22+ (`.nvmrc`; run `nvm use` at repo root; not required when using Docker)
- [Intuit Developer](https://developer.intuit.com/) account with a QBO app

## Installation

```bash
# Backend
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate

# Monorepo (from repo root)
npm install   # requires Node 22+ (.nvmrc, engine-strict in .npmrc)
cp apps/admin/.env.example apps/admin/.env
cp apps/timesheet/.env.example apps/timesheet/.env
```

Configure `backend/.env`:

```env
QUICKBOOKS_CLIENT_ID=your_client_id
QUICKBOOKS_CLIENT_SECRET=your_client_secret
QUICKBOOKS_REDIRECT_URI=http://localhost:8000/api/quickbooks/callback
QUICKBOOKS_BASE_URL=development
ALLOW_REGISTRATION=true
FRONTEND_ADMIN_URL=http://localhost:5173
FRONTEND_TIMESHEET_URL=http://localhost:5174
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:5174
```

## Development

### Docker (recommended for portability)

Runs the Laravel API, admin, and timesheet dev servers in containers. No local PHP, Composer, or Node required beyond Docker.

**Prerequisites:** [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker Engine + Compose v2).

```bash
# One-time: copy env templates (skipped if files already exist)
npm run docker:setup

# Edit backend/.env with your QuickBooks credentials, then build and start the stack
npm run docker:build
npm run docker:up
```

| Service | URL |
|---------|-----|
| API | http://localhost:8000 |
| Admin | http://localhost:5173 |
| Timesheet | http://localhost:5174 |

Useful commands:

```bash
npm run docker:up:build  # rebuild images then start (after Dockerfile changes)
npm run docker:smoke     # wait for API/admin/timesheet, health, and login via admin proxy
npm run docker:logs      # follow all service logs
npm run docker:down      # stop containers
npm run docker:reset     # stop and remove volumes (fresh node_modules vendor cache)
```

Docker and local non-Docker dev both use **SQLite** (`backend/database/database.sqlite`, configured in `backend/.env`). **MySQL is for production only** (see Production below). Tests always use in-memory SQLite.

On startup, Docker runs `php artisan db:seed` after migrations when `APP_ENV=local` and `DEV_SEED_ENABLED=true` (set in `docker-compose.yml` and `backend/.env.example`). Passwords are reset to the `DEV_SEED_*` values on each seed run, including every API container restart. Default dev accounts (override via `backend/.env`):

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@ellr.local` | `password` |
| Timesheet user | `timesheet@ellr.local` | `password` |

Frontends call the API through the Vite dev proxy (`VITE_API_URL=/api` in Docker) so Sanctum session cookies work on `localhost:5173` and `localhost:5174`. If you have an existing `apps/admin/.env` or `apps/timesheet/.env` with `VITE_API_URL=http://localhost:8000/api`, update it to `VITE_API_URL=/api` (see `apps/*/.env.example`). Optional port overrides: `API_PORT`, `ADMIN_PORT`, `TIMESHEET_PORT` in `docker/.env` (copy from `docker/.env.example`).

`node-init` installs npm dependencies once before admin and timesheet start. Lock file changes (`package-lock.json`, `composer.lock`) trigger a fresh install on the next container start.

### Local (without Docker)

```bash
# Terminal 1: Laravel API (port 8000)
npm run dev:api

# Terminal 2: Admin (port 5173)
npm run dev:admin

# Terminal 3: Timesheet (port 5174)
npm run dev:timesheet
```

## API endpoints

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/health` | API health check |
| POST | `/api/register` | Register (if `ALLOW_REGISTRATION=true`) |
| POST | `/api/login` | Sanctum sign-in |
| POST | `/api/logout` | Sign out (auth) |
| GET | `/api/user` | Current user (auth) |
| PATCH | `/api/user/qbo-employee` | Link a QBO employee to the account (auth) |
| GET | `/api/quickbooks/connect` | OAuth2 authorization URL (auth) |
| GET | `/api/quickbooks/callback` | Intuit OAuth callback |
| GET | `/api/quickbooks/status` | QBO connection status (auth) |
| POST | `/api/quickbooks/disconnect` | Disconnect QBO (auth) |
| GET | `/api/time-activities` | List TimeActivity records (auth) |
| POST | `/api/time-activities` | Create a time entry (auth) |
| GET | `/api/time-activities/{id}` | TimeActivity detail (auth) |
| PATCH | `/api/time-activities/{id}` | Update a time entry (auth) |
| DELETE | `/api/time-activities/{id}` | Delete a time entry (auth) |

### List time activities (`GET /api/time-activities`)

Optional query parameters:

| Parameter | Description |
|-----------|-------------|
| `start_position` | QuickBooks `STARTPOSITION` (1-based, default `1`) |
| `max_results` | Page size (default config cap, max `QUICKBOOKS_TIME_ACTIVITIES_MAX_RESULTS`) |

Response includes pagination metadata:

```json
{
  "data": [ /* TimeActivity rows */ ],
  "meta": {
    "count": 25,
    "max_results": 100,
    "start_position": 1,
    "truncated": false
  }
}
```

`meta.truncated` is `true` when the page is full and either a probe query finds another row (`QUICKBOOKS_TIME_ACTIVITIES_PROBE_TRUNCATED=true`) or probing is disabled and `count` equals `max_results`.

Use `@ellr/api-client` `listTimeActivities()` for the `{ data, meta }` response shape.

## Production

Checklist before the first deploy (Laravel API + static Vite builds for admin/timesheet).

### Laravel (`backend/.env`)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

ALLOW_REGISTRATION=false
REQUIRE_EMAIL_VERIFICATION=true

DB_CONNECTION=mysql
# … database credentials

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
SESSION_DOMAIN=.yourdomain.com

CACHE_STORE=redis
QUEUE_CONNECTION=redis

QUICKBOOKS_CLIENT_ID=…
QUICKBOOKS_CLIENT_SECRET=…
QUICKBOOKS_REDIRECT_URI=https://api.yourdomain.com/api/quickbooks/callback
QUICKBOOKS_BASE_URL=production
QUICKBOOKS_EXPOSE_API_ERRORS=false

FRONTEND_ADMIN_URL=https://admin.yourdomain.com
FRONTEND_TIMESHEET_URL=https://timesheet.yourdomain.com
FRONTEND_AUTH_URL=https://timesheet.yourdomain.com
SANCTUM_STATEFUL_DOMAINS=admin.yourdomain.com,timesheet.yourdomain.com

MAIL_MAILER=smtp
MAIL_HOST=…
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=no-reply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Intuit Developer Portal

- Redirect URI: must match `QUICKBOOKS_REDIRECT_URI`
- Production mode when connecting real QBO companies
- Scopes: `com.intuit.quickbooks.accounting` (already configured)

### Frontends

```bash
# Build variables (e.g. apps/admin/.env.production)
VITE_API_URL=https://api.yourdomain.com/api

npm run build:admin
npm run build:timesheet
```

Serve `apps/admin/dist` and `apps/timesheet/dist` over HTTPS (CDN, nginx, etc.). Apps call only the Laravel API via `@ellr/api-client`.

### Security

- HTTPS required on the API and both frontends
- `ALLOW_REGISTRATION=false`: no public registration
- `QUICKBOOKS_EXPOSE_API_ERRORS=false`: do not expose Intuit error bodies
- OAuth tokens encrypted in DB (`quickbooks_tokens`), never logged
- CORS/Sanctum: front domains in `SANCTUM_STATEFUL_DOMAINS`; `config/cors.php` with `supports_credentials`
- Session cookies: parent `SESSION_DOMAIN` (e.g. `.yourdomain.com`), `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`
- QBO employee per user (`qbo_employee_ref`): unique per account, set from admin, validated by the API
- QuickBooks OAuth: administrators connect in the admin app; timesheet users reuse the latest administrator token when they have no token of their own
- `REQUIRE_EMAIL_VERIFICATION=true`: block sign-in and protected routes until the user confirms email
- Password reset: `POST /api/forgot-password` and `POST /api/reset-password` (configure SMTP or another mail driver)
- Promote at least one administrator (`users.is_admin = 1`) before connecting QuickBooks

### Rotating `APP_KEY`

`APP_KEY` encrypts QuickBooks OAuth tokens in `quickbooks_tokens`. Rotating it without preparation makes existing tokens unreadable.

1. Schedule maintenance and disconnect QuickBooks for all users (or accept a forced reconnect).
2. Set the previous key in `APP_PREVIOUS_KEYS` (comma-separated) before deploying the new `APP_KEY`.
3. Deploy the new `APP_KEY`, run `php artisan config:cache`, and verify decrypt works for existing rows.
4. Ask administrators to reconnect QuickBooks if tokens were not re-encrypted.
5. Remove old keys from `APP_PREVIOUS_KEYS` after all tokens have been refreshed or replaced.

For greenfield rotation with no live tokens, update `APP_KEY` and run `php artisan key:generate` on a fresh environment only.

### Pre-release validation

```bash
npm run qa:finance
npm run prepush
php artisan migrate --force   # on the target environment
```

The project uses the [official Intuit PHP SDK](https://github.com/intuit/QuickBooks-V3-PHP-SDK) for:

- OAuth 2.0 (connect and refresh token)
- `TimeActivity` entity (timesheet CRUD)
- Accounting API v3 requests

Docs: https://developer.intuit.com/app/developer/qbo/docs/develop/sdks-and-samples-collections/php

## Structure

```
ellr-qbo-timesheet/
├── backend/                 # Laravel API
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   ├── Models/
│   │   └── Services/QuickBooksService.php
│   ├── config/quickbooks.php
│   ├── features/api/        # Behat scenarios
│   └── routes/api.php
├── packages/
│   └── api-client/          # Shared HTTP client (@ellr/api-client)
├── apps/
│   ├── admin/               # Admin UI (QBO connect, config)
│   └── timesheet/           # Time entry UI
├── .cursor/rules/           # Cursor rules (PHP, React, language, monorepo)
├── .vscode/                 # Shared VS Code / Cursor settings (committed)
├── .editorconfig            # Indentation and EOL for all editors
└── package.json             # npm workspaces + quality scripts
```

## Quality and local guardrails

Philosophy: do not manually re-read generated code; enforce strong constraints (tests, metrics, mutation testing, Git hooks).

### Husky (pre-commit / pre-push)

Husky v9+ format: one command per hook file (e.g. `npm run precommit`), no `husky.sh`.

| Hook | Command | Checks |
|------|---------|--------|
| `pre-commit` | `npm run precommit` | `lint:fast` (oxlint, Pint, PHPCS, PHPMD) + typecheck (workspaces in parallel) |
| `pre-push` | `npm run prepush` | Full lint (deps + jscpd) + `lint:dup:tests` + typecheck + coverage (frontend + backend in parallel with Behat) + PHPStan + Pest/Stryker mutation + parallel Vite builds |

Automatic install via `npm install` (`prepare` script).

### GitHub Actions

`.github/workflows/ci.yml` runs `npm run prepush` on every push and pull request to `main`/`master` (remote guardrail when hooks are bypassed).

### IDE setup (VS Code / Cursor)

Shared workspace config is **committed** so every developer gets the same defaults.

| File | Purpose |
|------|---------|
| `.vscode/settings.json` | Pint on save (PHP), workspace TypeScript, oxlint diagnostics |
| `.vscode/extensions.json` | Recommended extensions (install prompt on open) |
| `.vscode/tasks.json` | `lint`, `qa`, `dev:*` npm tasks |
| `.editorconfig` | 2 spaces (TS/JSON), 4 spaces (PHP), LF line endings |
| `.nvmrc` | Node 22 (use `nvm use` or `fnm use`) |
| `.npmrc` | `engine-strict=true` (npm install fails below Node 22) |
| `backend/.php-version` | PHP 8.3 (asdf / phpbrew) |

**First open:** accept the recommended extensions (EditorConfig, Laravel Pint, Intelephense, Oxc, PHP_CodeSniffer Community). Run `npm install` at the repo root and `composer install` in `backend/`, then **Developer: Reload Window**.

Prettier is intentionally **not** used (oxlint + Pint are the formatters of record).

**TypeScript:** use the workspace SDK when Cursor/VS Code asks (`js/ts.tsdk.promptToUseWorkspaceVersion`).

**oxlint / JSDoc in the IDE:** the **Oxc** extension (`oxc.oxc-vscode`) runs `node_modules/.bin/oxlint` on save. Each file needs a top-level `@file` sentence plus JSDoc on public exports (`jsdoc-js/require-file-overview` and `jsdoc-js/require-jsdoc`).

**PHP in the IDE:** use **only** `phpcscommunity.php-codesniffer` (`phpSniffer.*` in `.vscode/settings.json`). The wrapper `scripts/phpcs` always runs **PHPCS 4** from `backend/vendor/bin` (never the global `~/.composer/vendor/bin/phpcs` 3.x). If you see `Referenced sniff ... does not exist` **or** `SyntaxError: Unexpected token 'E'`, disable **`obliviousharmony.vscode-php-codesniffer`** (and any other PHPCS extension): it conflicts with `phpcscommunity` and parses PHPCS errors as JSON. Reload the window after `composer install` in `backend/`.

| Need | Tool |
|------|------|
| JSDoc / oxlint in the IDE | **Oxc** (`oxc.*` in `.vscode/settings.json`) |
| JSDoc / oxlint in CI and hooks | `npm run lint` or `npm run lint:frontend` |
| Lint all frontend workspaces from Tasks | **Tasks: Run Task** → `lint:frontend` (oxlint problem matcher) |
| TypeScript types in the IDE | Workspace TypeScript SDK (`js/ts.tsdk.path`) |
| Typecheck in CI and hooks | `npm run typecheck` |
| PHP format on save | **Laravel Pint** (recommended extension) |
| PHPCS / PHPDoc in the IDE | **PHP_CodeSniffer Community** (`phpSniffer.*` in `.vscode/settings.json`) |
| PHPCS / PHPDoc in CI and hooks | `npm run lint` or `cd backend && composer phpcs` |
| Lint whole backend from Tasks | **Tasks: Run Task** → `lint:phpcs` |
| Smoke test PHPCS from monorepo root | `npm run lint:phpcs` |

PHPCS covers first-party backend PHP (`app/`, `routes/`, `bootstrap/`, `config/`, factories, seeders, Behat bootstrap, `public/`). It excludes `tests/` and `database/migrations/`. Route and console closures are included: add a docblock if `composer phpcs` reports a missing function comment.

### Coverage thresholds

| Zone | Lines / stmts / funcs | Branches |
|------|------------------------|----------|
| Backend (Pest) | 85 % | included in `--min=85` |
| Frontend (Vitest) | 85 % | 75 % |
| `packages/api-client` | 85 % | 75 % |

### Mutation testing

| Zone | Tool | Command | Threshold |
|------|------|---------|-----------|
| Backend | Pest `--mutate` | `npm run test:mutation:backend` | score ≥ 90 % |
| Frontend | Stryker | `npm run test:mutation:frontend` | break ≥ 55 %, low ≥ 65 %, high ≥ 80 % |

`npm run test:mutation:frontend` passes on **admin**, **timesheet**, and **@ellr/api-client** (~85 %, ~91 %, ~93 %). **low 65 %** and **high 80 %** validated.

### Local commands

| Command | Usage |
|---------|-------|
| `npm run qa` | Quick: lint + types + coverage + Behat + parallel builds (no PHPStan or mutation) |
| `npm run prepush` | Full pre-push: qa + PHPStan + mutation + parallel builds (Husky hook equivalent) |
| `npm run qa:finance` | Standalone coverage + back/front mutation (~1–2 min); rerun without full prepush |
| `cd backend && composer qa` | Backend only: Pint + PHPCS + PHPStan + coverage + arch + Behat |

```bash
npm run qa
npm run qa:finance
# alias: npm run validate:thresholds

# Backend only
cd backend && composer qa

# Unit + feature tests (Pest)
cd backend && composer test

# Gherkin (Behat)
cd backend && composer test:behat

# Backend coverage (min 85 %)
cd backend && composer test:coverage

# Backend mutation (min 90 %)
cd backend && composer test:mutation

# Frontend (admin, timesheet, or api-client)
npm run test --workspace=admin
npm run test:coverage --workspace=timesheet
npm run test:mutation --workspace=admin
npm run test:coverage --workspace=@ellr/api-client
```

### Cursor (agents and review)

| File | Role |
|------|------|
| `AGENTS.md` | Agent instructions per monorepo area |
| `backend/AGENTS.md`, `apps/*/AGENTS.md`, `packages/api-client/AGENTS.md` | Per-package details |
| `.cursor/rules/language.mdc` | **English** as primary language (always active) |
| `.cursor/rules/monorepo-commands.mdc` | Commands, hooks, quality thresholds (always active) |
| `.cursor/rules/git-and-pr-workflow.mdc` | Commits on request, PRs via `gh` (always active) |
| `.cursor/rules/copywriting.mdc` | No em dash, English UI copy (always active) |
| `.cursor/rules/jsdoc.mdc` / `phpdoc.mdc` | Required documentation on public exports |
| `.cursor/rules/laravel-backend.mdc` | PHP / Pest conventions |
| `.cursor/rules/react-frontend.mdc` | React / Stryker conventions |
| `.cursor/BUGBOT.md` | Review rules (`/review-bugbot`) |

### Test layout

```
backend/
├── tests/Unit/              # Unit tests (Pest)
├── tests/Feature/           # API integration tests (Pest)
├── tests/Arch/              # Architecture tests (Pest)
└── features/api/            # Gherkin scenarios (Behat)

packages/api-client/src/
├── api.ts                   # apiFetch, ApiError, getApiErrorMessage
├── auth.ts                  # login, logout, fetchCurrentUser
└── api.test.ts              # HTTP client tests

apps/admin/src/
└── App.test.tsx             # Component tests (mock @ellr/api-client)

apps/timesheet/src/
└── App.test.tsx             # Component tests (mock @ellr/api-client)
```

## License

Proprietary Ellr
