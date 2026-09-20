---
name: ellr-finish-qa
description: Run the ellr-qbo-timesheet quality gates that match the files you changed before declaring a coding task done. Use after backend, frontend, or shared-package edits, and before telling Luc the work is finished.
---

# Finish QA (ellr-qbo-timesheet)

Do not validate by reading code only. Run the smallest set that covers what you touched, then widen if a hook would catch it.

## By layer

| Touched | Run |
|---------|-----|
| TS/TSX | `npm run lint` and `npm run typecheck` |
| `packages/api-client` | plus `npm run test --workspace=@ellr/api-client` |
| `apps/admin` or `apps/timesheet` | plus the workspace `test` script; update `App.test.tsx` for visible UI |
| `packages/ui` | plus `npm run test --workspace=@ellr/ui`; both `en.ts` and `fr.ts` if copy changed |
| `backend/app` | `cd backend && composer lint && composer analyse && composer test` (or the focused Pest file) |
| API route behavior | plus `composer test:behat` when a Gherkin scenario applies |
| Structure / new controller | `cd backend && composer test:arch` |

## Thresholds (do not lower them)

- Backend coverage 85 %, mutation ≥ 90 % (`covers(ClassName::class)` on Pest files).
- Frontend coverage 85 % lines/stmts/funcs, 75 % branches. Stryker low ≥ 65 %, high ≥ 80 %.

## Broader gates

- Local stand-in for pre-commit: `npm run precommit`
- Coverage + mutation only: `npm run qa:finance`
- Full local QA: `npm run qa` (slow; use when the change spans layers)

Do not commit unless Luc asked. Do not use `--no-verify`.
