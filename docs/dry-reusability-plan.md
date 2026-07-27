# Plan DRY et réutilisabilité

Scan du dépôt (juillet 2026). Objectif : une source de vérité par responsabilité, composants et fonctions réutilisables entre admin et timesheet.

## Carte actuelle

```
packages/api-client/     ✅ HTTP, auth, erreurs FR (bien centralisé)
apps/admin/App.tsx       ⚠️  auth + login UI dupliqués
apps/timesheet/App.tsx   ⚠️  auth + login UI dupliqués
backend/QuickBooksService   ✅ OAuth, tokens, DataService
backend/TimeActivityController  ❌ CRUD QBO + token + ownership (~300 lignes)
```

| Zone | Fichiers | Lignes approx. | État DRY |
|------|----------|----------------|----------|
| Client HTTP | `packages/api-client` | ~200 | Excellent |
| UI auth/login | 2× `App.tsx` | ~120 dupliquées | Faible |
| UI layout/alertes | 2× `App.tsx` | ~60 dupliquées | Faible |
| Tests composants | 2× `App.test.tsx` | ~200 dupliquées | Faible |
| Config Vite/Vitest | 2× apps | 8 fichiers identiques | Faible |
| API métier QBO | `TimeActivityController` | 1 gros fichier | Moyen |
| OAuth / tokens | `QuickBooksService` | 1 service | Bon |

---

## Inventaire des duplications

### Frontend (priorité haute)

| ID | Élément dupliqué | Où | Cible de réutilisation |
|----|------------------|-----|------------------------|
| F1 | État auth (`user`, `email`, `password`, `authLoading`) | admin + timesheet `App.tsx` | Hook `useAuth` |
| F2 | `handleLogin` / `handleLogout` | admin + timesheet | Hook `useAuth` |
| F3 | Formulaire login (courriel, mot de passe, bouton) | admin + timesheet | `LoginForm` |
| F4 | Écran `Chargement...` | admin + timesheet | `LoadingScreen` |
| F5 | Header + bouton déconnexion | admin + timesheet | `AppShell` |
| F6 | Alertes error/success/warning (classes Tailwind) | admin + timesheet | `Alert` |
| F7 | `vi.mock('@ellr/api-client')` | 2× `App.test.tsx` | `@ellr/test-utils` |
| F8 | Scénarios test login/logout | 2× `App.test.tsx` | `loginFormActions()` |
| F9 | `apiFetch` inline (QBO, time-activities) | admin + timesheet | `quickbooks.ts`, `timesheet.ts` dans api-client |
| F10 | `vitest.config.ts`, `setup.ts`, `main.tsx`, `index.css` | 2 apps | Config partagée ou package |

### Backend (priorité moyenne)

| ID | Élément dupliqué | Où | Cible de réutilisation |
|----|------------------|-----|------------------------|
| B1 | CRUD TimeActivity + SDK direct | `TimeActivityController` | `TimeActivityService` |
| B2 | `resolveToken`, refresh, erreurs QBO | `TimeActivityController` | Trait `ResolvesQuickBooksToken` |
| B3 | FindById → erreur → ownership → 404 | show/update/destroy | `findTimeActivityForUser()` |
| B4 | Validation `description` max 4000 | store + update | Form Requests |
| B5 | Codes `error` JSON | controllers + api-client TS | Enum PHP `ApiErrorCode` |
| B6 | Setup mock QBO dans tests | `TimeActivityTest` (~30×) | Helpers Pest |
| B7 | Régénération session login/register | `AuthController` | Méthode privée (mineur) |

### Déjà bien fait (ne pas casser)

- `apiFetch`, CSRF, `getApiErrorMessage`, `login`/`logout`/`fetchCurrentUser`
- `QuickBooksService` (OAuth, state, refresh lock, tokens chiffrés)
- `QuickBooksAuthController`, `AuthController` (minces)
- Règles Arch Pest (`*Service`, pas de `dd`)
- Alias Vite vers `@ellr/api-client` source

---

## Architecture cible

```
packages/
  api-client/          # Tout le réseau + domaine API (auth, qbo, timesheet)
  ui/                  # NOUVEAU : composants + hooks partagés
  test-utils/          # NOUVEAU : mocks Vitest partagés
  vite-config/         # NOUVEAU (optionnel phase 3) : createAppConfig(port)

apps/admin/            # Écran QBO + employé uniquement
apps/timesheet/        # Formulaire temps uniquement

backend/
  Services/
    QuickBooksService.php      # OAuth, tokens, DataService
    TimeActivityService.php    # NOUVEAU : CRUD + ownership
  Http/Concerns/
    ResolvesQuickBooksToken.php  # NOUVEAU : token + erreurs HTTP QBO
  Enums/
    ApiErrorCode.php           # NOUVEAU : contrat avec api-client
```

---

## Phases d'exécution

### Phase 1 : API client complet (P0, ~1 jour)

**But :** plus aucun `apiFetch('/...')` dans les apps.

| Tâche | Fichiers | Critère done |
|-------|----------|--------------|
| 1.1 `quickbooks.ts` | `fetchQuickBooksStatus`, `connectQuickBooks`, `disconnectQuickBooks` | admin n'importe plus `apiFetch` |
| 1.2 `timesheet.ts` | `createTimeActivity(payload)` | timesheet n'importe plus `apiFetch` |
| 1.3 Tests | `api.test.ts` | couverture + mutation inchangées |
| 1.4 Rules | `react-frontend.mdc` | liste des exports domaine à jour |

### Phase 2 : UI partagée `@ellr/ui` (P0, ~1-2 jours)

**But :** admin et timesheet ne gèrent plus l'auth/login en double.

| Composant / hook | Responsabilité | Consommateurs |
|------------------|----------------|---------------|
| `useAuth()` | bootstrap, login, logout, `user`, `authLoading` | admin, timesheet |
| `useFlashMessage()` | error/success/notice unifié | admin, timesheet |
| `LoginForm` | courriel, mot de passe, submit, erreur | admin, timesheet |
| `Alert` | variantes error/success/warning | admin, timesheet |
| `LoadingScreen` | chargement session | admin, timesheet |
| `AppShell` | layout + titre + logout | admin, timesheet |
| `tokens.ts` | `inputClass`, classes boutons | composants ui |

**Critère done :**
- `App.tsx` admin < 150 lignes, timesheet < 120 lignes
- zéro copier-coller du formulaire login entre les deux apps
- tests composants passent (mock `@ellr/api-client` inchangé côté apps)

**Structure package :**

```
packages/ui/
  package.json          # peer: react 19
  src/
    index.ts
    hooks/useAuth.ts
    hooks/useFlashMessage.ts
    components/LoginForm.tsx
    components/Alert.tsx
    components/LoadingScreen.tsx
    components/AppShell.tsx
    styles/tokens.ts
  vitest.config.ts
  src/*.test.tsx
```

### Phase 3 : Tests partagés `@ellr/test-utils` (P1, ~0.5 jour)

| Export | Usage |
|--------|-------|
| `createApiClientMock()` | `vi.mock` factory |
| `authenticatedUser` | fixture User |
| `fillLoginForm(user)` | actions RTL répétées |
| `expectMessageClasses(el, type)` | assertion alerte |

**Critère done :** réduction ≥ 25 % des lignes dans les deux `App.test.tsx`.

### Phase 4 : Backend services (P0 backend, ~2 jours)

| Tâche | Détail |
|-------|--------|
| 4.1 `TimeActivityService` | index/store/show/update/destroy, payloads, ownership, query filtrée |
| 4.2 `ResolvesQuickBooksToken` | extrait de `TimeActivityController` |
| 4.3 Controller mince | validation → service → JSON (~80 lignes) |
| 4.4 Form Requests | `StoreTimeActivityRequest`, `UpdateTimeActivityRequest` |
| 4.5 `ApiErrorCode` enum | sync avec `getApiErrorMessage` |
| 4.6 Helpers Pest | `actingAsWithQboEmployee()`, `mockQboDataService()` |

**Critère done :**
- `TimeActivityController` sans import `QuickBooksOnline\API\Facades`
- tests Feature + mutation ≥ 80 % inchangés
- PHPStan OK

### Phase 5 : Config partagée (P2, ~0.5 jour)

- `packages/vite-config/createAppConfig({ port })`
- vitest base partagé (`findMonorepoRoot`, seuils 85/75)
- admin/timesheet : 3-5 lignes de config chacun

**Ne faire que si** une 3e app ou un nouveau package UI est ajouté, sauf si le temps le permet en fin de phase 2.

---

## Ce qu'on ne refactorise pas (volontairement)

| Élément | Raison |
|---------|--------|
| Deux apps séparées admin/timesheet | déploiements et ports distincts |
| `App.tsx` monolithique par app | acceptable tant que < 200 lignes après phase 2 |
| Design system complet (Button, FormField) | ROI faible avec 2 écrans ; phase 5+ si croissance |
| Middleware `qbo_employee` | un seul consommateur aujourd'hui |
| Extraire OAuth callback parser | admin seul ; attendre réutilisation |

---

## Checklist revue (chaque PR)

- [ ] La logique existe-t-elle déjà dans `api-client`, `ui`, ou un service Laravel ?
- [ ] Un nouveau `apiFetch` dans une app est-il justifié (sinon api-client) ?
- [ ] Un bloc copié entre admin/timesheet doit-il aller dans `@ellr/ui` ?
- [ ] Un appel SDK QBO hors `*Service` est-il refusé ?
- [ ] Tests : helper Pest / test-utils avant copier un setup mock ?
- [ ] Code `error` backend aligné avec `getApiErrorMessage` ?

---

## Métriques de succès

| Métrique | Avant | Cible post-phase 2+4 |
|----------|-------|----------------------|
| Lignes dupliquées login/auth (2 apps) | ~120 | 0 (dans `@ellr/ui`) |
| `apiFetch` dans apps | 4 appels | 0 |
| Lignes `TimeActivityController` | ~300 | < 100 |
| Fichiers config identiques (2 apps) | 8 | 0-2 (wrappers) |
| Packages partagés | 1 | 3-4 |

---

## Ordre recommandé pour l'agent / l'équipe

1. Phase 1 (api-client domaine) : risque faible, gain immédiat DRY rules
2. Phase 2 (`@ellr/ui`) : plus visible pour les développeurs
3. Phase 3 (test-utils) : en parallèle ou juste après phase 2
4. Phase 4 (backend services) : avant tout nouveau endpoint QBO
5. Phase 5 (vite-config) : si besoin

Chaque phase = une PR reviewable, tests verts, `npm run qa:finance` avant merge release.
