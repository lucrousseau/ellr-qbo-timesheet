---
paths:
  - "apps/**/*.{ts,tsx}"
  - "packages/api-client/**/*.{ts,tsx}"
  - "packages/ui/**/*.{ts,tsx}"
  - "packages/test-utils/**/*.{ts,tsx}"
  - "packages/vite-config/**/*.{ts,tsx}"
---

# Frontend React

Canonical detail: `.cursor/rules/react-frontend.mdc`, `.cursor/rules/jsdoc.mdc`, `apps/*/AGENTS.md`, `packages/api-client/AGENTS.md`.

## Boundaries

- HTTP only through `@ellr/api-client`. No `fetch` to Intuit, no second client in `apps/*/src/lib/`.
- UI primitives from `@ellr/ui`. Apps do not import `@headlessui/react`.
- Errors: `getApiErrorMessage` plus stable API codes. Do not duplicate copy.
- `App.tsx` is a composition root (target ≤ ~80 lines). Feature state in `hooks/`, presentational UI in `components/`.

## Tests

- Mock `@ellr/api-client` in app tests. Shared HTTP changes: `packages/api-client/src/api.test.ts`.
- Visible UI change: update the app's `App.test.tsx` (or the colocated component test).
- Coverage: 85 % lines/stmts/funcs, 75 % branches. Stryker: break ≥ 55 %, low ≥ 65 %, high ≥ 80 %.

## JSDoc

- Every linted source file: `@file` header (one English sentence after the tag).
- Public exports: description, `@param` (no `{type}`), `@returns` when the function returns a value.
- Tests and `vite-env.d.ts` are excluded.

```typescript
/**
 * @file Time entry API helpers for the timesheet app.
 */

/**
 * Creates a time activity for the signed-in user's QBO employee.
 * @param payload Time range and optional description.
 * @returns Created QuickBooks activity data.
 */
```
