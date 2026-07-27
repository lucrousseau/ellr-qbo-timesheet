# Timesheet (React)

User interface to record time. Talks to the Laravel API which pushes to QBO.

See also the [root README](../../README.md) and [`packages/api-client/AGENTS.md`](../../packages/api-client/AGENTS.md).

## Stack

- React 19 + TypeScript 6 + Vite 8 + Tailwind CSS 4
- Dev port: **5174**
- API: `@ellr/api-client` (`VITE_API_URL`, default `http://localhost:8000/api`)

## Commands

```bash
npm run dev --workspace=timesheet
npm run lint --workspace=timesheet
npm run typecheck --workspace=timesheet
npm run test --workspace=timesheet
npm run test:coverage --workspace=timesheet
npm run test:mutation --workspace=timesheet
npm run build --workspace=timesheet
```

## Conventions

- Time entry form via `POST /api/time-activities` (Sanctum auth required on the API).
- API calls via `@ellr/api-client`, never direct fetch to QBO (DRY).
- QBO employee: assigned on the account (admin), not entered in the form (business rule on the API).
- Errors: `getApiErrorMessage` only; no duplicated messages.
- **JSDoc** on the `App` component and every new export; see `.cursor/rules/jsdoc.mdc` and `.cursor/rules/language.mdc`.
- Validation on the backend; the frontend sends fields documented in the API.
- Tests with Testing Library + Vitest.

## Key files

```
src/App.tsx          # Time entry form
src/App.test.tsx     # Component tests (mock @ellr/api-client)
src/test/setup.ts    # Vitest setup + cleanup
```

## Required tests

- Form or submit behavior change: update `App.test.tsx`.
- Shared HTTP client change: `packages/api-client/src/api.test.ts`.

## Quality thresholds

- Coverage: 85 % lines/stmts/funcs, 75 % branches.
- Stryker mutation: break ≥ 55 %, low ≥ 65 %, high ≥ 80 % (validated, score ~91 %).
