# Review rules (Bugbot / local review)

## Backend PHP

- Every modified file in `backend/app/` must have an associated Pest test (Feature or Unit).
- Use `covers(ClassName::class)` for Pest mutation testing.
- **PHPDoc** on public/protected classes and methods; `composer phpcs` enforces Squiz + Slevomat.
- No business logic in controllers: delegate to services (SRP, DIP).
- No direct QBO SDK calls outside `QuickBooksService` (DRY).
- No duplicated business validation or error mapping already centralized.
- No hardcoded secrets, tokens, or credentials.
- No `eval()`, `exec()`, `shell_exec()`, or `system()`.

## Frontend React

- No direct `fetch` to domains other than `VITE_API_URL`.
- No duplicated HTTP client in apps: use `@ellr/api-client` (DRY).
- No duplicated business error messages: `getApiErrorMessage` + API codes.
- No TypeScript `any` without a comment justification.
- **JSDoc** on every public export (`packages/*`, `App` in apps); `npm run lint` enforces `require-jsdoc`. User-facing copy in **English**.
- Changes in `packages/api-client/`: update `api.test.ts` (and `auth.ts` if applicable).
- Visible UI change in an app: component test required (`App.test.tsx`).
- Before extracting a shared admin/timesheet component: confirm the logic belongs in `api-client` or the backend instead.

## QBO security

- OAuth tokens only in the database (`quickbooks_tokens`), never logged.
- Sensitive API routes protected by `auth:sanctum`.
- `ALLOW_REGISTRATION=false` in production to disable public registration.
- Do not expose Intuit error bodies in prod (`QUICKBOOKS_EXPOSE_API_ERRORS`).

## Architecture

- Architecture test violations (`tests/Arch/`): blocking.
- New endpoint without a documented entry in `routes/api.php`: flag it.
- Admin/timesheet duplication or SDK outside services: see `docs/dry-reusability-plan.md`.

## Quality

- Coverage thresholds: 85 % backend/frontend, 75 % frontend branches.
- Mutation thresholds: backend ≥ 80 % (Pest, ~87 %), frontend low ≥ 65 % / high ≥ 80 % (Stryker).
- Do not disable PHPStan, Pint, PHPCS, oxlint, or tests to pass a PR.
- Do not commit without explicit author request.
- Do not suggest `--no-verify` on Husky hooks.
