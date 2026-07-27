# Backend Laravel (Ellr QBO Timesheet)

API REST pour QuickBooks Online. Seul composant du monorepo autorisé à communiquer avec Intuit.

Documentation détaillée : [AGENTS.md](./AGENTS.md) et [README racine](../README.md).

## Commandes rapides

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

composer test              # Pest
composer test:coverage     # Couverture min 85 %
composer test:behat        # Scénarios Gherkin
composer test:mutation     # Mutation min 80 %
composer analyse           # PHPStan niveau 5
composer format:check      # Pint
```

## Stack

PHP 8.3, Laravel 13, Sanctum, SDK `quickbooks/v3-php-sdk`, Pest 4, Behat, PHPStan.
