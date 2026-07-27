# DRY and reusability plan

Repository scan (July 2026). Goal: one source of truth per responsibility, reusable components and functions across admin and timesheet.

**Status (July 2026):** phases 1 through 5 implemented; SOLID file-structure refactor and automated arch checks applied.

## Current map

```
packages/api-client/     ✅ HTTP, auth, quickbooks, timesheet, EN errors
packages/ui/             ✅ useAuth, LoginForm, Alert, AppShell, tokens
packages/test-utils/     ✅ buildApiClientMock, fillLoginForm, fixtures
packages/vite-config/    ✅ Vite, Vitest, Stryker, oxlint, main, CSS, test setup
apps/admin/              ✅ hooks/ + components/ + thin App.tsx (~88 lines)
apps/timesheet/          ✅ components/ + thin App.tsx (~118 lines)
backend/Services/        ✅ QuickBooksService, TimeActivityService, QboEmployeeAuthorizationService, QuickBooksTokenResolverService
backend/Controllers/     ✅ One domain per controller (Auth, QboEmployee, QuickBooksAuth, TimeActivity)
```

| Area | DRY status |
|------|------------|
| HTTP client (`@ellr/api-client`) | Excellent |
| Auth/login/layout UI (`@ellr/ui`) | Excellent |
| Component tests (`@ellr/test-utils`) | Good |
| Vite/Vitest/Stryker config (`@ellr/vite-config`) | Good |
| QBO business API backend | Good |
| OAuth / tokens (`QuickBooksService`) | Good |
| File structure (hooks, components, services) | Good |

---

## Inventory: resolved vs remaining

### Frontend: resolved

| ID | Item | Resolution |
|----|------|------------|
| F1–F6 | Auth, login, layout, alerts | `@ellr/ui` |
| F7–F8 | Mocks and test actions | `@ellr/test-utils` (`buildApiClientMock`, `fillLoginForm`) |
| F9 | `apiFetch` in apps | `quickbooks.ts`, `timesheet.ts`, `auth.ts` |
| F10 | Duplicated config | `@ellr/vite-config` (Vite, Vitest, Stryker, oxlint, `main`, CSS, setup) |
| F11 | Monolithic `App.tsx` | `hooks/` + `components/` per app (see `.cursor/rules/file-structure.mdc`) |

### Frontend: remaining (intentional or future)

| Item | Note |
|------|------|
| `timesheet.ts` only exposes `createTimeActivity` | API list/update/delete CRUD has no TS client until a screen consumes them |
| UI tests in `@ellr/ui` | Package has no dedicated component tests (`--passWithNoTests`) |

### Backend: resolved

| ID | Item | Resolution |
|----|------|------------|
| B1 | TimeActivity CRUD | `TimeActivityService` |
| B2 | Token + refresh | `QuickBooksTokenResolverService` |
| B3 | Employee ownership | `QboEmployeeAuthorizationService` |
| B4 | Max validation | `StoreTimeActivityRequest`, `UpdateTimeActivityRequest`, `UpdateQboEmployeeRequest` |
| B5 | `error` codes | `ApiErrorCode` enum |
| B6 | Test setup | `actingAsWithQboEmployee()` in `Pest.php` |
| B7 | QBO employee mapping endpoint | `QboEmployeeController` (not `AuthController`) |
| Duplicated QBO API errors | `QuickBooksService::apiErrorJsonResponse()` |

### Backend: remaining (minor)

| Item | Note |
|------|------|
| B8 | Session regeneration on login/register in `AuthController` (acceptable) |
| `personal_access_tokens` | Sanctum migration; cookie SPA mode without `createToken()` |

---

## Target architecture (achieved)

```
packages/
  api-client/          # Network + API domain
  ui/                  # Shared components + hooks
  test-utils/          # Shared Vitest mocks
  vite-config/         # createAppConfig, createVitestConfig, createStrykerConfig, shared assets

apps/admin/            # hooks/ + components/ + thin App.tsx
apps/timesheet/        # components/ + thin App.tsx

backend/
  Http/Controllers/Api/
    AuthController.php
    QboEmployeeController.php
    QuickBooksAuthController.php
    TimeActivityController.php
  Http/Requests/
  Services/
    QuickBooksService.php
    TimeActivityService.php
    QboEmployeeAuthorizationService.php
    QuickBooksTokenResolverService.php
  Enums/ApiErrorCode.php
```

---

## Automated structure checks

| Tool | Scope | Command |
|------|-------|---------|
| Pest Arch | PHP layers, line counts, QBO SDK boundaries | `composer test:arch` |
| PHPMD | PHP class length (250+), complexity | `composer phpmd` |
| dependency-cruiser | TS import boundaries (components vs api-client) | `npm run lint:deps` |
| jscpd (source) | Copy-paste across apps/packages/backend (max 5 % lines) | `npm run lint:dup` (pre-push) |
| jscpd (tests) | Copy-paste in test suites (max 12 % lines) | `npm run lint:dup:tests` (pre-push) |

See `.cursor/rules/file-structure.mdc` for soft limits.

---

## Execution phases (completed)

| Phase | Deliverable | Status |
|-------|-------------|--------|
| 1 | `quickbooks.ts`, `timesheet.ts` | ✅ |
| 2 | `@ellr/ui` | ✅ |
| 3 | `@ellr/test-utils` | ✅ |
| 4 | `TimeActivityService`, Form Requests, `QuickBooksTokenResolverService` | ✅ |
| 5 | `@ellr/vite-config` (+ shared assets post-audit) | ✅ |
| 6 | File-structure refactor + arch tooling | ✅ |

---

## What we do not refactor (by design)

| Item | Reason |
|------|--------|
| Two apps admin/timesheet | Separate deployments and ports |
| `App.tsx` per app | Composition root only; feature UI in `hooks/` and `components/` |
| Full design system | Low ROI with 2 screens |
| TS client for list/update/delete time activities | No consuming UI |
| Split `QuickBooksService` | Under 250 lines; cohesive OAuth + tokens |

---

## Review checklist (every PR)

- [ ] Does the logic already exist in `api-client`, `ui`, or a Laravel service?
- [ ] Is a new `apiFetch` in an app justified (otherwise use api-client)?
- [ ] Should a copied block between admin/timesheet move to `@ellr/ui` or `@ellr/vite-config`?
- [ ] Is a QBO SDK call outside `*Service` rejected? (`composer test:arch`)
- [ ] Do `App.tsx` and API controllers stay under soft limits? (Pest Arch + `.cursor/rules/file-structure.mdc`)
- [ ] Tests: Pest helper / `buildApiClientMock` before copying a mock setup?
- [ ] Backend `error` code aligned with `getApiErrorMessage`?

---

## Success metrics (achieved)

| Metric | Target | Current |
|--------|--------|---------|
| Duplicated login/auth lines (2 apps) | 0 in `@ellr/ui` | ✅ |
| `apiFetch` in apps | 0 | ✅ |
| `TimeActivityController` lines | < 100 | ~111 |
| `App.tsx` (admin / timesheet) | < ~80 / thin | ~88 / ~118 |
| Shared packages | 3–4 | 4 |
| Identical app config (Vite/Vitest/Stryker/oxlint/CSS/setup) | Centralized | ✅ |

---

## Recommended next steps

1. Add `listTimeActivities` / `updateTimeActivity` / `deleteTimeActivity` in api-client when a new screen consumes them.
2. Component tests for `@ellr/ui` if the package grows.
3. Hoist app `tsconfig` if a third React app is added.

Each change = reviewable PR, green tests, `npm run qa:finance` before a release merge.
