---
paths:
  - "backend/**/*.php"
  - "backend/features/**/*.feature"
---

# Laravel backend

Canonical detail: `.cursor/rules/laravel-backend.mdc`, `.cursor/rules/phpdoc.mdc`, `backend/AGENTS.md`.

## Structure

- Controllers in `app/Http/Controllers/Api/`: thin JSON orchestration, one domain per class. Pest Arch enforces `< 125` lines.
- Form Requests for validation. Services for business logic and QBO.
- Do not call the QBO SDK outside `app/Services/` (plus token conversion on the model).
- Use `$request->user()` plus `QuickBooksTokenResolverService::resolve($user)`. Avoid `auth()->user()`.
- At the controller line limit, inline user/token per action. Do not add a private `userAndToken()` helper unless the Pest Arch limit is raised in the same change.
- Multi-tenant: scope admin APIs by `organization_id`. Super-admins have `organization_id = null`.

## Tests

- New or changed `app/` class: Pest test with `covers(ClassName::class)`.
- New or changed API route behavior: Feature test plus a Behat scenario when applicable.
- Mock `QuickBooksService`. Never call Intuit in tests.
- Coverage ≥ 85 %. Mutation score ≥ 90 %. PHPStan level 5.

## PHPDoc

Required on first-party backend PHP in PHPCS scope (`backend/phpcs.xml`): file header, class, and methods (`@param`, `@return`). English. Tests and migrations stay lean.

```php
<?php

/**
 * REST API route definitions for health, auth, QuickBooks OAuth, and time activities.
 */
```

## Commands

```bash
cd backend && composer lint && composer analyse && composer test
```
