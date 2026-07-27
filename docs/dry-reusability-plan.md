# Plan DRY et réutilisabilité

Scan du dépôt (juillet 2026). Objectif : une source de vérité par responsabilité, composants et fonctions réutilisables entre admin et timesheet.

**Statut (juillet 2026) :** phases 1 à 5 implémentées et nettoyage post-audit appliqué.

## Carte actuelle

```
packages/api-client/     ✅ HTTP, auth, quickbooks, timesheet, erreurs FR
packages/ui/             ✅ useAuth, LoginForm, Alert, AppShell, tokens
packages/test-utils/     ✅ buildApiClientMock, fillLoginForm, fixtures
packages/vite-config/    ✅ Vite, Vitest, Stryker, oxlint, main, CSS, setup test
apps/admin/App.tsx       ✅ écran métier QBO + employé (~215 lignes)
apps/timesheet/App.tsx   ✅ formulaire feuille de temps (~159 lignes)
backend/TimeActivityService  ✅ CRUD QBO + ownership
backend/TimeActivityController  ✅ mince (~76 lignes)
```

| Zone | État DRY |
|------|----------|
| Client HTTP (`@ellr/api-client`) | Excellent |
| UI auth/login/layout (`@ellr/ui`) | Excellent |
| Tests composants (`@ellr/test-utils`) | Bon |
| Config Vite/Vitest/Stryker (`@ellr/vite-config`) | Bon |
| API métier QBO backend | Bon |
| OAuth / tokens (`QuickBooksService`) | Bon |

---

## Inventaire : résolu vs restant

### Frontend : résolu

| ID | Élément | Résolution |
|----|---------|------------|
| F1–F6 | Auth, login, layout, alertes | `@ellr/ui` |
| F7–F8 | Mocks et actions test | `@ellr/test-utils` (`buildApiClientMock`, `fillLoginForm`) |
| F9 | `apiFetch` dans apps | `quickbooks.ts`, `timesheet.ts`, `auth.ts` |
| F10 | Config dupliquée | `@ellr/vite-config` (Vite, Vitest, Stryker, oxlint, `main`, CSS, setup) |

### Frontend : restant (volontaire ou futur)

| Item | Note |
|------|------|
| `timesheet.ts` n'expose que `createTimeActivity` | CRUD list/update/delete côté API sans client TS tant qu'aucun écran ne les consomme |
| Tests UI dans `@ellr/ui` | Package sans tests composants dédiés (`--passWithNoTests`) |
| `App.tsx` admin > 150 lignes | Logique métier QBO employé + OAuth, pas du copier-coller |

### Backend : résolu

| ID | Élément | Résolution |
|----|---------|------------|
| B1 | CRUD TimeActivity | `TimeActivityService` |
| B2 | Token + refresh | `ResolvesQuickBooksToken` |
| B3 | Ownership find | `findActivityForUser()` dans le service |
| B4 | Validation max | `StoreTimeActivityRequest`, `UpdateTimeActivityRequest` |
| B5 | Codes `error` | `ApiErrorCode` enum |
| B6 | Setup tests | `actingAsWithQboEmployee()` dans `Pest.php` |
| Erreurs API QBO dupliquées | `QuickBooksService::apiErrorJsonResponse()` |

### Backend : restant (mineur)

| Item | Note |
|------|------|
| B7 | Régénération session login/register dans `AuthController` (acceptable) |
| `personal_access_tokens` | Migration Sanctum ; mode cookie SPA sans `createToken()` |

---

## Architecture cible (atteinte)

```
packages/
  api-client/          # Réseau + domaine API
  ui/                  # Composants + hooks partagés
  test-utils/          # Mocks Vitest partagés
  vite-config/         # createAppConfig, createVitestConfig, createStrykerConfig, assets partagés

apps/admin/            # Écran QBO + employé
apps/timesheet/        # Formulaire temps

backend/
  Services/
    QuickBooksService.php
    TimeActivityService.php
  Http/Concerns/ResolvesQuickBooksToken.php
  Enums/ApiErrorCode.php
```

---

## Phases d'exécution (terminées)

| Phase | Livrable | Statut |
|-------|----------|--------|
| 1 | `quickbooks.ts`, `timesheet.ts` | ✅ |
| 2 | `@ellr/ui` | ✅ |
| 3 | `@ellr/test-utils` | ✅ |
| 4 | `TimeActivityService`, Form Requests, trait | ✅ |
| 5 | `@ellr/vite-config` (+ assets partagés post-audit) | ✅ |

---

## Ce qu'on ne refactorise pas (volontairement)

| Élément | Raison |
|---------|--------|
| Deux apps admin/timesheet | Déploiements et ports distincts |
| `App.tsx` par app | Écrans métier distincts, taille raisonnable |
| Design system complet | ROI faible avec 2 écrans |
| Client TS pour list/update/delete time activities | Pas d'UI consommatrice |

---

## Checklist revue (chaque PR)

- [ ] La logique existe-t-elle déjà dans `api-client`, `ui`, ou un service Laravel ?
- [ ] Un nouveau `apiFetch` dans une app est-il justifié (sinon api-client) ?
- [ ] Un bloc copié entre admin/timesheet doit-il aller dans `@ellr/ui` ou `@ellr/vite-config` ?
- [ ] Un appel SDK QBO hors `*Service` est-il refusé ?
- [ ] Tests : helper Pest / `buildApiClientMock` avant copier un setup mock ?
- [ ] Code `error` backend aligné avec `getApiErrorMessage` ?

---

## Métriques de succès (atteintes)

| Métrique | Cible | Actuel |
|----------|-------|--------|
| Lignes dupliquées login/auth (2 apps) | 0 dans `@ellr/ui` | ✅ |
| `apiFetch` dans apps | 0 | ✅ |
| Lignes `TimeActivityController` | < 100 | ~76 |
| Packages partagés | 3–4 | 4 |
| Config identique apps (Vite/Vitest/Stryker/oxlint/CSS/setup) | Centralisée | ✅ |

---

## Ordre recommandé pour la suite

1. Ajouter `listTimeActivities` / `updateTimeActivity` / `deleteTimeActivity` dans api-client si un nouvel écran les consomme.
2. Tests composants `@ellr/ui` si le package grossit.
3. Hoister `tsconfig` apps si une 3e app React est ajoutée.

Chaque changement = PR reviewable, tests verts, `npm run qa:finance` avant merge release.
