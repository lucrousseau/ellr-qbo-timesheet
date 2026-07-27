# Ellr QBO Timesheet

Timesheet minimaliste pour QuickBooks Online. Monorepo avec API Laravel, client HTTP partagé et deux interfaces React.

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

Les frontends communiquent uniquement avec l'API Laravel via le package `@ellr/api-client`. Seul le backend parle à QuickBooks Online via le SDK officiel.

## Stack (juillet 2026)

| Composant | Version |
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
| Stryker | 9.6 (mutation frontend) |

## Prérequis

- PHP 8.3+
- Composer 2.x
- Node.js 22+
- Compte [Intuit Developer](https://developer.intuit.com/) avec une app QBO

## Installation

```bash
# Backend
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate

# Monorepo (depuis la racine)
npm install
cp apps/admin/.env.example apps/admin/.env
cp apps/timesheet/.env.example apps/timesheet/.env
```

Configurer dans `backend/.env` :

```env
QUICKBOOKS_CLIENT_ID=votre_client_id
QUICKBOOKS_CLIENT_SECRET=votre_client_secret
QUICKBOOKS_REDIRECT_URI=http://localhost:8000/api/quickbooks/callback
QUICKBOOKS_BASE_URL=development
ALLOW_REGISTRATION=true
FRONTEND_ADMIN_URL=http://localhost:5173
FRONTEND_TIMESHEET_URL=http://localhost:5174
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:5174
```

## Démarrage

```bash
# Terminal 1 : API Laravel (port 8000)
npm run dev:api

# Terminal 2 : Admin (port 5173)
npm run dev:admin

# Terminal 3 : Timesheet (port 5174)
npm run dev:timesheet
```

## Endpoints API

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/health` | Santé de l'API |
| POST | `/api/register` | Inscription (si `ALLOW_REGISTRATION=true`) |
| POST | `/api/login` | Connexion Sanctum |
| POST | `/api/logout` | Déconnexion (auth) |
| GET | `/api/user` | Utilisateur courant (auth) |
| GET | `/api/quickbooks/connect` | URL d'autorisation OAuth2 (auth) |
| GET | `/api/quickbooks/callback` | Callback OAuth2 Intuit |
| GET | `/api/quickbooks/status` | Statut connexion QBO (auth) |
| POST | `/api/quickbooks/disconnect` | Déconnexion QBO (auth) |
| GET | `/api/time-activities` | Liste des TimeActivity (auth) |
| POST | `/api/time-activities` | Créer une entrée de temps (auth) |
| GET | `/api/time-activities/{id}` | Détail d'une TimeActivity (auth) |
| PATCH | `/api/time-activities/{id}` | Modifier une entrée de temps (auth) |
| DELETE | `/api/time-activities/{id}` | Supprimer une entrée de temps (auth) |

## Production

Checklist avant le premier déploiement (API Laravel + builds Vite statiques pour admin/timesheet).

### Laravel (`backend/.env`)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.votredomaine.com

ALLOW_REGISTRATION=false

DB_CONNECTION=mysql
# … credentials base de données

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=redis
QUEUE_CONNECTION=redis

QUICKBOOKS_CLIENT_ID=…
QUICKBOOKS_CLIENT_SECRET=…
QUICKBOOKS_REDIRECT_URI=https://api.votredomaine.com/api/quickbooks/callback
QUICKBOOKS_BASE_URL=production
QUICKBOOKS_EXPOSE_API_ERRORS=false

FRONTEND_ADMIN_URL=https://admin.votredomaine.com
FRONTEND_TIMESHEET_URL=https://timesheet.votredomaine.com
SANCTUM_STATEFUL_DOMAINS=admin.votredomaine.com,timesheet.votredomaine.com
```

### Intuit Developer Portal

- Redirect URI : identique à `QUICKBOOKS_REDIRECT_URI`
- App en mode **Production** quand vous connectez de vrais comptes QBO
- Scopes : `com.intuit.quickbooks.accounting` (déjà configuré)

### Frontends

```bash
# Variables build (ex. apps/admin/.env.production)
VITE_API_URL=https://api.votredomaine.com/api

npm run build:admin
npm run build:timesheet
```

Servir `apps/admin/dist` et `apps/timesheet/dist` en HTTPS (CDN, nginx, etc.). Les apps n'appellent que l'API Laravel via `@ellr/api-client`.

### Sécurité

- HTTPS obligatoire sur l'API et les deux frontends
- `ALLOW_REGISTRATION=false` : pas d'inscription publique
- `QUICKBOOKS_EXPOSE_API_ERRORS=false` : ne pas exposer les corps d'erreur Intuit
- Tokens OAuth chiffrés en base (`quickbooks_tokens`), jamais loggés
- CORS/Sanctum : domaines front listés dans `SANCTUM_STATEFUL_DOMAINS`

### Validation pré-release

```bash
npm run qa:finance
npm run prepush
php artisan migrate --force   # sur l'environnement cible
```

Le projet utilise le [SDK PHP officiel d'Intuit](https://github.com/intuit/QuickBooks-V3-PHP-SDK) pour :

- OAuth 2.0 (connexion et refresh token)
- Entité `TimeActivity` (CRUD feuille de temps)
- Requêtes vers l'API Accounting v3

Documentation : https://developer.intuit.com/app/developer/qbo/docs/develop/sdks-and-samples-collections/php

## Structure

```
ellr-qbo-timesheet/
├── backend/                 # API Laravel
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   ├── Models/
│   │   └── Services/QuickBooksService.php
│   ├── config/quickbooks.php
│   ├── features/api/        # Scénarios Behat
│   └── routes/api.php
├── packages/
│   └── api-client/          # Client HTTP partagé (@ellr/api-client)
├── apps/
│   ├── admin/               # Interface admin (connexion QBO, config)
│   └── timesheet/           # Interface saisie de temps
├── .cursor/rules/           # Règles Cursor (PHP, React, monorepo)
└── package.json             # Workspaces npm + scripts qualité
```

## Qualité et garde-fous locaux

Philosophie : ne pas relire le code généré, mais l'entourer de contraintes fortes (tests, métriques, mutation testing, hooks Git).

### Husky (pre-commit / pre-push)

Hooks au format Husky v9+ : une commande par fichier (ex. `npm run precommit`), sans `husky.sh`.

| Hook | Commande | Vérifie |
|------|----------|---------|
| `pre-commit` | `npm run precommit` | oxlint (apps + api-client), Pint, TypeScript |
| `pre-push` | `npm run prepush` | Lint + typecheck + couverture 85 % + arch + Behat + PHPStan + builds |

Installation automatique via `npm install` (script `prepare`).

### Seuils de couverture

| Zone | Lignes / stmts / funcs | Branches |
|------|------------------------|----------|
| Backend (Pest) | 85 % | inclus dans `--min=85` |
| Frontend (Vitest) | 85 % | 75 % |
| `packages/api-client` | 85 % | 75 % |

### Mutation testing

| Zone | Outil | Commande | Seuil |
|------|-------|----------|-------|
| Backend | Pest `--mutate` | `npm run test:mutation:backend` | score ≥ 80 % |
| Frontend | Stryker | `npm run test:mutation:frontend` | break ≥ 55 %, low ≥ 65 %, high ≥ 80 % |

`npm run test:mutation:frontend` passe sur **admin**, **timesheet** et **@ellr/api-client** (scores ~85 %, ~91 %, ~93 %). Seuils **low 65 %** et **high 80 %** validés.

### Commandes locales

| Commande | Usage |
|----------|-------|
| `npm run qa` | Rapide : lint + types + couverture + Behat + builds (sans arch ni PHPStan) |
| `npm run prepush` | Complet avant push : qa + arch + PHPStan (équivalent hook Husky) |
| `npm run qa:finance` | Avant release : couverture + mutation back/front (~1–2 min) |
| `cd backend && composer qa` | Backend seul : Pint + PHPStan + couverture + arch + Behat |

```bash
npm run qa
npm run qa:finance
# alias : npm run validate:thresholds

# Backend seul
cd backend && composer qa

# Tests unitaires + feature (Pest)
cd backend && composer test

# Tests Gherkin (Behat)
cd backend && composer test:behat

# Couverture backend (min 85 %)
cd backend && composer test:coverage

# Mutation backend (min 80 %)
cd backend && composer test:mutation

# Frontend (admin, timesheet ou api-client)
npm run test --workspace=admin
npm run test:coverage --workspace=timesheet
npm run test:mutation --workspace=admin
npm run test:coverage --workspace=@ellr/api-client
```

### Cursor (agents et revue)

| Fichier | Rôle |
|---------|------|
| `AGENTS.md` | Instructions agent par zone du monorepo |
| `backend/AGENTS.md`, `apps/*/AGENTS.md`, `packages/api-client/AGENTS.md` | Détails par package |
| `.cursor/rules/monorepo-commands.mdc` | Commandes, hooks, seuils qualité (toujours actif) |
| `.cursor/rules/git-and-pr-workflow.mdc` | Commits sur demande, PR via `gh` (toujours actif) |
| `.cursor/rules/copywriting.mdc` | Pas de tiret cadratin, copy FR (toujours actif) |
| `.cursor/rules/laravel-backend.mdc` | Conventions PHP / Pest |
| `.cursor/rules/react-frontend.mdc` | Conventions React / Stryker |
| `.cursor/BUGBOT.md` | Règles de revue (`/review-bugbot`) |

### Structure des tests

```
backend/
├── tests/Unit/              # Tests unitaires (Pest)
├── tests/Feature/           # Tests d'intégration API (Pest)
├── tests/Arch/              # Tests d'architecture (Pest)
└── features/api/            # Scénarios Gherkin (Behat)

packages/api-client/src/
├── api.ts                   # apiFetch, ApiError, getApiErrorMessage
├── auth.ts                  # login, logout, fetchCurrentUser
└── api.test.ts              # Tests client HTTP

apps/admin/src/
└── App.test.tsx             # Tests composants (mock @ellr/api-client)

apps/timesheet/src/
└── App.test.tsx             # Tests composants (mock @ellr/api-client)
```

## Licence

Propriétaire Ellr
