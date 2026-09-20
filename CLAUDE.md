@AGENTS.md

# Claude Code

Claude Code does not read `AGENTS.md` or `.cursor/rules/*.mdc` on its own. This file imports the canonical briefing. Path-scoped rules in `.claude/rules/` load when matching files are opened. Nested `CLAUDE.md` files in `backend/`, `apps/*`, and `packages/*` load on demand. Personal notes: `CLAUDE.local.md` (gitignored).

When a convention changes, update `AGENTS.md` and `.cursor/rules/` first, then the distilled `.claude/rules/` file if it would otherwise drift.

## Hard constraints

- Do not commit, push, merge, or deploy unless Luc explicitly asks.
- Do not commit `.env`, QBO tokens, Intuit secrets, or private keys.
- Do not call Intuit/QBO from the frontend. Only `backend/` uses the SDK, and only inside services.
- Do not add a second HTTP client in apps. Use `@ellr/api-client`.
- Do not duplicate API error copy. Map codes with `resolveApiError` and `getApiErrorMessage`.
- Do not invent SiteGround hostnames, usernames, or paths. Connect with `ssh ellr-timesheet-sg`. Live identity is in gitignored `.cursor/rules/siteground-ssh.local.mdc`.
- Do not paste `.env` contents or private keys into chat.
- Do not use `--no-verify` on Husky unless Luc explicitly asks.
- Do not force-push `main`.

## Language and copy

- Code, comments, JSDoc, PHPDoc, docs, commits, and PR prose: English.
- Match Luc's chat language (often French). Keep the repository English.
- New UI strings: both `packages/ui/src/i18n/messages/en.ts` and `fr.ts`.
- No em dash (U+2014). Use a comma, semicolon, colon, or parentheses.
- API `error` codes stay stable English snake_case.

## Before finishing a coding task

Use the `ellr-finish-qa` skill. Minimum for the files you touched:

- TypeScript: `npm run lint` and `npm run typecheck`
- PHP: `cd backend && composer lint && composer analyse` plus Pest for the change
- Tests for the behavior you changed (Pest `covers()`, Vitest, Behat when a route changes)
- Do not declare UI work done without exercising the flow (browser, or the closest substitute)

## Git

- Commits only on request. Message: 1–2 English sentences on why, via HEREDOC.
- PRs: `gh pr create` with summary plus test plan. Return the URL.
- Use `gh` for GitHub. Do not change `git config`.

## Production

Agents may SSH to inspect the live SiteGround Shared deploy (`ssh ellr-timesheet-sg`). Prefer PHP 8.3 on shared hosting. Deploy is manual GitHub Actions (`.github/workflows/deploy-siteground.yml`) from `main`. See the `ellr-siteground` skill and `docs/siteground-shared-hosting.md`.

## Where to look

| Need | File |
|------|------|
| Architecture and commands | `AGENTS.md` |
| Per-package conventions | `backend/AGENTS.md`, `apps/*/AGENTS.md`, `packages/*/AGENTS.md` |
| Cursor rules (canonical detail) | `.cursor/rules/` |
| DRY plan | `docs/dry-reusability-plan.md` |
| QBO time-activity sync | `docs/quickbooks-time-activity-sync.md` |
| Personal notes | `CLAUDE.local.md` |
