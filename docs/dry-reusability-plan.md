# DRY and reusability plan

Repository scan (July 2026). Goal: one source of truth per responsibility, reusable components and functions across admin and timesheet.

**Status (July 2026):** phases 1 through 5 implemented and post-audit cleanup applied.

## Current map

```
packages/api-client/     ✅ HTTP, auth, quickbooks, timesheet, EN errors
packages/ui/             ✅ useAuth, LoginForm, Alert, AppShell, tokens
packages/test-utils/     ✅ buildApiClientMock, fillLoginForm, fixtures
packages/vite-config/    ✅ Vite, Vitest, Stryker, oxlint, main, CSS, test setup
apps/admin/App.tsx       ✅ QBO + employee business screen (~215 lines)
apps/timesheet/App.tsx   ✅ timesheet form (~159 lines)
backend/TimeActivityService  ✅ QBO CRUD + ownership
backend/TimeActivityController  ✅ thin (~76 lines)
```

| Area | DRY status |
|------|------------|
| HTTP client (`@ellr/api-client`) | Excellent |
| Auth/login/layout UI (`@ellr/ui`) | Excellent |
| Component tests (`@ellr/test-utils`) | Good |
| Vite/Vitest/Stryker config (`@ellr/vite-config`) | Good |
| QBO business API backend | Good |
| OAuth / tokens (`QuickBooksService`) | Good |

---

## Inventory: resolved vs remaining

### Frontend: resolved

| ID | Item | Resolution |
|----|------|------------|
| F1–F6 | Auth, login, layout, alerts | `@ellr/ui` |
| F7–F8 | Mocks and test actions | `@ellr/test-utils` (`buildApiClientMock`, `fillLoginForm`) |
| F9 | `apiFetch` in apps | `quickbooks.ts`, `timesheet.ts`, `auth.ts` |
| F10 | Duplicated config | `@ellr/vite-config` (Vite, Vitest, Stryker, oxlint, `main`, CSS, setup) |

### Frontend: remaining (intentional or future)

| Item | Note |
|------|------|
| `timesheet.ts` only exposes `createTimeActivity` | API list/update/delete CRUD has no TS client until a screen consumes them |
| UI tests in `@ellr/ui` | Package has no dedicated component tests (`--passWithNoTests`) |
| Admin `App.tsx` > 150 lines | QBO employee + OAuth business logic, not copy-paste |

### Backend: resolved

| ID | Item | Resolution |
|----|------|------------|
| B1 | TimeActivity CRUD | `TimeActivityService` |
| B2 | Token + refresh | `ResolvesQuickBooksToken` |
| B3 | Ownership find | `findActivityForUser()` in the service |
| B4 | Max validation | `StoreTimeActivityRequest`, `UpdateTimeActivityRequest` |
| B5 | `error` codes | `ApiErrorCode` enum |
| B6 | Test setup | `actingAsWithQboEmployee()` in `Pest.php` |
| Duplicated QBO API errors | `QuickBooksService::apiErrorJsonResponse()` |

### Backend: remaining (minor)

| Item | Note |
|------|------|
| B7 | Session regeneration on login/register in `AuthController` (acceptable) |
| `personal_access_tokens` | Sanctum migration; cookie SPA mode without `createToken()` |

---

## Target architecture (achieved)

```
packages/
  api-client/          # Network + API domain
  ui/                  # Shared components + hooks
  test-utils/          # Shared Vitest mocks
  vite-config/         # createAppConfig, createVitestConfig, createStrykerConfig, shared assets

apps/admin/            # QBO + employee screen
apps/timesheet/        # Time entry form

backend/
  Services/
    QuickBooksService.php
    TimeActivityService.php
  Http/Concerns/ResolvesQuickBooksToken.php
  Enums/ApiErrorCode.php
```

---

## Execution phases (completed)

| Phase | Deliverable | Status |
|-------|-------------|--------|
| 1 | `quickbooks.ts`, `timesheet.ts` | ✅ |
| 2 | `@ellr/ui` | ✅ |
| 3 | `@ellr/test-utils` | ✅ |
| 4 | `TimeActivityService`, Form Requests, trait | ✅ |
| 5 | `@ellr/vite-config` (+ shared assets post-audit) | ✅ |

---

## What we do not refactor (by design)

| Item | Reason |
|------|--------|
| Two apps admin/timesheet | Separate deployments and ports |
| `App.tsx` per app | Distinct business screens, reasonable size |
| Full design system | Low ROI with 2 screens |
| TS client for list/update/delete time activities | No consuming UI |

---

## Review checklist (every PR)

- [ ] Does the logic already exist in `api-client`, `ui`, or a Laravel service?
- [ ] Is a new `apiFetch` in an app justified (otherwise use api-client)?
- [ ] Should a copied block between admin/timesheet move to `@ellr/ui` or `@ellr/vite-config`?
- [ ] Is a QBO SDK call outside `*Service` rejected?
- [ ] Tests: Pest helper / `buildApiClientMock` before copying a mock setup?
- [ ] Backend `error` code aligned with `getApiErrorMessage`?

---

## Success metrics (achieved)

| Metric | Target | Current |
|--------|--------|---------|
| Duplicated login/auth lines (2 apps) | 0 in `@ellr/ui` | ✅ |
| `apiFetch` in apps | 0 | ✅ |
| `TimeActivityController` lines | < 100 | ~76 |
| Shared packages | 3–4 | 4 |
| Identical app config (Vite/Vitest/Stryker/oxlint/CSS/setup) | Centralized | ✅ |

---

## Recommended next steps

1. Add `listTimeActivities` / `updateTimeActivity` / `deleteTimeActivity` in api-client when a new screen consumes them.
2. Component tests for `@ellr/ui` if the package grows.
3. Hoist app `tsconfig` if a third React app is added.

Each change = reviewable PR, green tests, `npm run qa:finance` before a release merge.
