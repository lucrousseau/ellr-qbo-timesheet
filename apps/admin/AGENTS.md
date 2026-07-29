# Admin (React)

Administration UI: QuickBooks Online connection, OAuth status, configuration.

See also the [root README](../../README.md) and [`packages/api-client/AGENTS.md`](../../packages/api-client/AGENTS.md).

## Stack

- React 19 + TypeScript 6 + Vite 8 + Tailwind CSS 4
- Dev port: **5173**
- API: `@ellr/api-client` (`VITE_API_URL`, default `http://localhost:8000/api`)

## Commands

```bash
npm run dev --workspace=admin
npm run lint --workspace=admin
npm run typecheck --workspace=admin
npm run test --workspace=admin
npm run test:coverage --workspace=admin
npm run test:mutation --workspace=admin
npm run build --workspace=admin
```

## Conventions

- API calls via `@ellr/api-client`, never direct fetch to QBO (DRY).
- Functional components; network and error logic in the shared package (SRP).
- QBO employee config: `updateQboEmployee`; no duplicated business validation in the UI.
- **JSDoc** on the `App` component and every new export; see `.cursor/rules/jsdoc.mdc` and `.cursor/rules/language.mdc`.
- Utility Tailwind classes, no custom CSS except `index.css`.
- **File layout**: thin `App.tsx`, feature logic in `hooks/`, presentational UI in `components/` (see `.cursor/rules/file-structure.mdc`).
- Tests: `*.test.tsx` next to the code under test.

## Key files

```
apps/admin/src/
  App.tsx                              # Composition root (auth gate + layout)
  hooks/useQuickBooksAdmin.ts          # Auth, locale, QuickBooks OAuth state
  hooks/useTimesheetProvisioning.ts    # QBO employee invite/remove
  components/AdminDashboard.tsx        # Preferences + Integrations tabs
  components/AccountPanel.tsx          # Preferences tab (locale, password)
  components/QuickBooksConnectionPanel.tsx
  components/TimesheetUserProvisioningPanel.tsx
  App.test.tsx                         # Component tests (mock @ellr/api-client)
  test/setup.ts
```

## Required tests

- Any visible UI change: update `App.test.tsx`.
- Shared HTTP client change: `packages/api-client/src/api.test.ts`.

## Quality thresholds

- Coverage: 85 % lines/stmts/funcs, 75 % branches.
- Stryker mutation: break ≥ 55 %, low ≥ 65 %, high ≥ 80 % (validated, score ~85 %).
