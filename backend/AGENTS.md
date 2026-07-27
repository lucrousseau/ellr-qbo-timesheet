# Backend Laravel (API)

API REST pour QuickBooks Online. OAuth2 et entité `TimeActivity` via le SDK officiel `quickbooks/v3-php-sdk`.

## Arborescence clé

```
app/Http/Controllers/Api/   # Endpoints REST
app/Services/               # QuickBooksService
app/Models/                 # QuickBooksToken, User
routes/api.php              # Routes API
config/quickbooks.php       # Config OAuth QBO
tests/Feature/              # Tests Pest (API)
tests/Unit/                 # Tests unitaires
tests/Arch/                 # Tests d'architecture
features/api/               # Scénarios Gherkin (Behat)
```

## Commandes

```bash
composer test              # Pest (unit + feature + arch)
composer test:arch         # Règles d'architecture seules
composer test:behat        # Gherkin API
composer test:coverage     # Couverture min 85% (finance QBO)
composer analyse           # PHPStan niveau 5
composer format:check      # Pint (style)
composer format            # Corriger le style
composer test:mutation     # Pest mutate, score min 80%
composer qa                # format + analyse + couverture + arch + behat
```

## Conventions

- Controllers minces : validation, délégation au service, réponse JSON.
- Logique QBO dans `QuickBooksService`, pas dans les controllers.
- Modèle `QuickBooksToken` : table `quickbooks_tokens`.
- Routes protégées : middleware `auth:sanctum`.
- Mocker `QuickBooksService` dans les tests, jamais appeler l'API Intuit en test.

## Tests obligatoires

| Changement | Test requis |
|------------|-------------|
| Nouveau endpoint | `tests/Feature/*Test.php` + scénario Behat si applicable |
| Nouveau service / modèle | `tests/Unit/*` ou Feature avec mocks |
| Règle structurelle | `tests/Arch/ArchitectureTest.php` |

Utiliser `covers(ClassName::class)` dans Pest pour le mutation testing.

## Variables d'environnement

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
