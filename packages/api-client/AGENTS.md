# API Client (`@ellr/api-client`)

Shared TypeScript HTTP client for `apps/admin` and `apps/timesheet`. Single frontend network entry point to the Laravel API.

## Files

```
src/
├── api.ts          # apiFetch, ApiError, getApiErrorMessage, ensureCsrfCookie
├── auth.ts         # login, logout, fetchCurrentUser, updateQboEmployee, type User
├── quickbooks.ts   # fetchQuickBooksStatus, connect/disconnect, OAuth callback helpers
├── timesheet.ts    # createTimeActivity
├── index.ts        # Public re-exports
└── api.test.ts     # Unit tests (required when api.ts or auth.ts changes)
```

## Commands

```bash
npm run lint --workspace=@ellr/api-client
npm run typecheck --workspace=@ellr/api-client
npm run test --workspace=@ellr/api-client
npm run test:coverage --workspace=@ellr/api-client
npm run test:mutation --workspace=@ellr/api-client
```

## Thresholds

| Metric | Threshold |
|--------|-----------|
| Coverage (Vitest) | 85 % lines/stmts/funcs, 75 % branches |
| Mutation (Stryker) | break ≥ 55 %, low ≥ 65 %, high ≥ 80 % (validated, score ~93 %) |

## Conventions

- `apiFetch`: `credentials: 'include'` by default (Sanctum stateful), CSRF on mutations.
- `getApiErrorMessage`: user-facing messages in **English**, stable business codes (`quickbooks_not_connected`, `registration_disabled`, `qbo_employee_not_configured`, etc.): **single** source of truth for UI copy (DRY).
- `ApiError`: expose `status` and `code` for UI mapping.
- No calls to Intuit/QBO: only `VITE_API_URL`.
- **JSDoc** required on every public export; enforced by oxlint (`oxlint.base.json`).
- Public exports limited via `index.ts` (interface segregation).
- Domain modules (`quickbooks.ts`, `timesheet.ts`): apps call these functions, not `apiFetch` directly.

## Required tests

| Change | Required test |
|--------|----------------|
| `apiFetch` / headers / errors | `api.test.ts` |
| Auth (login, logout, user) | `api.test.ts` (auth helpers section) |
| New public export | `api.test.ts` + update `index.ts` |

Apps mock `@ellr/api-client` in component tests; do not duplicate HTTP logic in apps.
