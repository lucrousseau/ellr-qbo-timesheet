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

- PHP 8.3+ (`backend/.php-version` for version managers)
- Composer 2.x
- Node.js 22+ (`.nvmrc`; run `nvm use` at repo root)
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
npm install
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

## Production

Checklist before the first deploy (Laravel API + static Vite builds for admin/timesheet).

### Laravel (`backend/.env`)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

ALLOW_REGISTRATION=false

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
SANCTUM_STATEFUL_DOMAINS=admin.yourdomain.com,timesheet.yourdomain.com
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
- QBO employee per user (`qbo_employee_ref`): set from admin, validated by the API

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
| `pre-commit` | `npm run precommit` | oxlint (JSDoc), Pint + PHPCS, TypeScript |
| `pre-push` | `npm run prepush` | Lint + typecheck + 85 % coverage + arch + Behat + PHPStan + builds |

Automatic install via `npm install` (`prepare` script).

### IDE setup (VS Code / Cursor)

Shared workspace config is **committed** so every developer gets the same defaults.

| File | Purpose |
|------|---------|
| `.vscode/settings.json` | Pint on save (PHP), workspace TypeScript, oxlint diagnostics |
| `.vscode/extensions.json` | Recommended extensions (install prompt on open) |
| `.vscode/tasks.json` | `lint`, `qa`, `dev:*` npm tasks |
| `.editorconfig` | 2 spaces (TS/JSON), 4 spaces (PHP), LF line endings |
| `.nvmrc` | Node 22 (use `nvm use` or `fnm use`) |
| `backend/.php-version` | PHP 8.3 (asdf / phpbrew) |

**First open:** accept the recommended extensions (EditorConfig, Laravel Pint, Intelephense, Oxc). Reload the window if prompted.

**PHP on save:** requires `composer install` in `backend/` so `vendor/bin/pint` exists.

**TypeScript:** use the workspace SDK when Cursor/VS Code asks (`typescript.enablePromptUseWorkspaceTsdk`).

Prettier is intentionally **not** used (oxlint + Pint are the formatters of record).

### Coverage thresholds

| Zone | Lines / stmts / funcs | Branches |
|------|------------------------|----------|
| Backend (Pest) | 85 % | included in `--min=85` |
| Frontend (Vitest) | 85 % | 75 % |
| `packages/api-client` | 85 % | 75 % |

### Mutation testing

| Zone | Tool | Command | Threshold |
|------|------|---------|-----------|
| Backend | Pest `--mutate` | `npm run test:mutation:backend` | score ≥ 80 % |
| Frontend | Stryker | `npm run test:mutation:frontend` | break ≥ 55 %, low ≥ 65 %, high ≥ 80 % |

`npm run test:mutation:frontend` passes on **admin**, **timesheet**, and **@ellr/api-client** (~85 %, ~91 %, ~93 %). **low 65 %** and **high 80 %** validated.

### Local commands

| Command | Usage |
|---------|-------|
| `npm run qa` | Quick: lint + types + coverage + Behat + builds (no arch or PHPStan) |
| `npm run prepush` | Full pre-push: qa + arch + PHPStan (Husky hook equivalent) |
| `npm run qa:finance` | Before release: coverage + back/front mutation (~1–2 min) |
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

# Backend mutation (min 80 %)
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
