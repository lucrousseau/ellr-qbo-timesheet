# Backend Laravel (API)

REST API for QuickBooks Online. OAuth2 and `TimeActivity` entity via the official `quickbooks/v3-php-sdk`.

## Key layout

```
app/Http/Controllers/Api/   # REST endpoints (thin, one domain each)
app/Http/Requests/          # Store/UpdateTimeActivityRequest, UpdateQboEmployeeRequest
app/Services/               # QuickBooksService, TimeActivityService, QboEmployeeAuthorizationService, QuickBooksTokenResolverService
app/Models/                 # QuickBooksToken, User
routes/api.php              # API routes
config/quickbooks.php       # QBO OAuth config
tests/Feature/              # Pest API tests
tests/Unit/                 # Unit tests
tests/Arch/                 # Architecture tests
features/api/               # Gherkin scenarios (Behat)
```

## Commands

```bash
composer test              # Pest (unit + feature + arch)
composer test:arch         # Architecture rules only
composer test:behat        # API Gherkin
composer test:coverage     # Min 85 % coverage (QBO finance standard)
composer analyse           # PHPStan level 5
composer format:check      # Pint (style)
composer phpcs             # PHPDoc (first-party backend PHP; see phpcs.xml)
composer phpmd             # Class size and complexity (phpmd.xml)
composer lint              # Pint + PHPCS + PHPMD
composer format            # Fix Pint style
composer test:mutation     # Pest mutate, min 80 % score
composer qa                # format + analyse + coverage + arch + behat
```

## Conventions

- Thin controllers: validation, service delegation, JSON response (SRP).
- QBO logic in `QuickBooksService` and `TimeActivityService`, not in controllers (DRY + DIP).
- `TimeActivityController` delegates to the service; no direct SDK facades in the controller.
- `QuickBooksToken` model: `quickbooks_tokens` table.
- Protected routes: `auth:sanctum` middleware.
- Mock `QuickBooksService` in tests; never call the Intuit API in tests.
- Avoid bloated controllers: extract a service if auth, QBO mapping, or employee rules grow.
- **PHPDoc** on all code in `app/`; see `.cursor/rules/phpdoc.mdc` and `.cursor/rules/language.mdc`.

## Required tests

| Change | Required test |
|--------|----------------|
| New endpoint | `tests/Feature/*Test.php` + Behat scenario if applicable |
| New service / model | `tests/Unit/*` or Feature with mocks |
| Structural rule | `tests/Arch/ArchitectureTest.php` |

Use `covers(ClassName::class)` in Pest for mutation testing.

## Environment variables

```env
ALLOW_REGISTRATION=true
QUICKBOOKS_CLIENT_ID=
QUICKBOOKS_CLIENT_SECRET=
QUICKBOOKS_REDIRECT_URI=http://localhost:8000/api/quickbooks/callback
QUICKBOOKS_BASE_URL=development
QUICKBOOKS_EXPOSE_API_ERRORS=
FRONTEND_ADMIN_URL=http://localhost:5173
FRONTEND_TIMESHEET_URL=http://localhost:5174
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:5174
```
