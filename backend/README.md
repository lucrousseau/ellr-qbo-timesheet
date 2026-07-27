# Laravel backend (Ellr QBO Timesheet)

REST API for QuickBooks Online. The only monorepo component allowed to communicate with Intuit.

Detailed documentation: [AGENTS.md](./AGENTS.md) and [root README](../README.md).

## Quick commands

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

composer test              # Pest
composer test:coverage     # Min 85 % coverage
composer test:behat        # Gherkin scenarios
composer test:mutation     # Min 90 % mutation score
composer analyse           # PHPStan level 5
composer format:check      # Pint
```

## Stack

PHP 8.3, Laravel 13, Sanctum, `quickbooks/v3-php-sdk` SDK, Pest 4, Behat, PHPStan.
