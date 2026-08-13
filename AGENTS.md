# Ellr QBO Timesheet

Minimal QuickBooks Online timesheet monorepo.

**Project language: English** (code, UI, API messages, docs, rules, and agent instructions).

## Structure

| Folder | Role | Stack |
|--------|------|-------|
| `backend/` | Laravel REST API | PHP 8.3, Laravel 13, Sanctum, QBO SDK |
| `packages/api-client/` | Shared HTTP client | TypeScript, Vitest, Stryker |
| `packages/ui/` | Shared UI and design system | React 19, Headless UI, Tailwind 4 |
| `apps/admin/` | Admin UI (QBO connect) | React 19, Vite 8, Tailwind 4 |
| `apps/timesheet/` | User time entry | React 19, Vite 8, Tailwind 4 |

Frontends import `@ellr/api-client` and talk **only** to the Laravel API (`/api/*`). Only the backend communicates with QuickBooks Online.

## Essential commands

```bash
# Development
npm run dev:api          # Laravel :8000
npm run dev:admin        # Admin :5173
npm run dev:timesheet    # Timesheet :5174

# Quality (local, Husky)
npm run lint             # oxlint + Pint + PHPCS
npm run typecheck        # TypeScript (apps + api-client)
npm run test:coverage    # Vitest + Pest at 85 % thresholds
npm run qa               # lint + types + scripts/qa-tests.sh (parallel coverage, Behat, PHPStan, builds)
npm run qa:finance       # validate:thresholds (coverage + mutation)

# Backend
cd backend && composer test
cd backend && composer test:behat
cd backend && composer analyse
cd backend && composer test:mutation
```

## Agent rules

1. **Do not validate by reading code only**: run tests and Husky hooks.
2. **Do not commit** unless the user explicitly asks.
3. **DRY / SOLID**: follow `.cursor/rules/dry-solid.mdc` and `.cursor/rules/file-structure.mdc`; one responsibility per layer, no duplicated HTTP/QBO/errors.
4. Any backend change in `app/` must have an associated Pest test (`covers(ClassName::class)` for mutation).
5. Any API route behavior change should have a Behat scenario when applicable.
6. Any shared HTTP change: `packages/api-client/` + `api.test.ts`.
7. No direct frontend → QBO communication: everything goes through `backend/`.
8. Never commit `.env`, tokens, or Intuit secrets.
9. Run Pint + PHPCS (backend) and oxlint (TS/TSX) before finishing a task.
10. **JSDoc / PHPDoc** on public exports; project language is **English** (see `.cursor/rules/language.mdc`, `jsdoc.mdc`, `phpdoc.mdc`).
11. `npm run prepush` (Husky pre-push) includes coverage and mutation thresholds. Use `npm run qa:finance` for a standalone coverage + mutation rerun without the full prepush pipeline.
12. **Design system:** evolve `@ellr/ui` incrementally on every UI change; prefer Ellr wrappers over raw HTML controls in apps; see `.cursor/rules/ui-design-system.mdc`.

## Git hooks (Husky)

Husky v9+ format: `.husky/*` = npm command only (no `husky.sh`).

| Hook | Command | Checks |
|------|---------|--------|
| `pre-commit` | `npm run precommit` | `precommit:qa` (lint:fast + typecheck + Pest Arch + Unit + time-activity Feature) |
| `pre-push` | `npm run prepush` | sync policy + `qa` (lint + types + `scripts/qa-tests.sh`) + `lint:dup:tests` + Pest/Stryker mutation |

## Quality thresholds (finance)

| Metric | Backend | Frontend |
|--------|---------|----------|
| Coverage | 85 % (Pest) | 85 % lines, 75 % branches (Vitest) |
| Mutation | ≥ 90 % (Pest `--mutate`) | break ≥ 55 %, **low ≥ 65 %**, high ≥ 80 % (Stryker) |

Measured Stryker scores: admin ~85 %, timesheet ~91 %, api-client ~93 %.

## Cursor

- Rules: `.cursor/rules/` (`language.mdc`, `monorepo-commands.mdc`, `dry-solid.mdc` always active; `git-and-pr-workflow.mdc`, `copywriting.mdc`)
- Shared IDE: `.vscode/` (also used by Cursor), `.editorconfig`, `.nvmrc`
- PR review: `.cursor/BUGBOT.md` (includes DRY/SOLID checklist)
- Reusability plan: `docs/dry-reusability-plan.md`
- Project profitability roadmap (phased plan; strategy context for agents): `docs/project-profitability-roadmap.md`
- QuickBooks time activity sync (webhooks, reconcile, local dev): `docs/quickbooks-time-activity-sync.md`
- SiteGround Shared deploy (temporary; dual-mode with Cloud/VPS): `docs/siteground-shared-hosting.md`
- **Production SSH is available** for debug/validation: see `.cursor/rules/siteground-ssh.mdc` (`ssh -i ~/.ssh/ellr_timesheet_sg_deploy -p 18765 …`)
- Per-area details: `backend/AGENTS.md`, `packages/api-client/AGENTS.md`, `packages/ui/AGENTS.md`, `apps/*/AGENTS.md`
