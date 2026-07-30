<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevDatabaseSeeder;

beforeEach(function () {
    $this->app->detectEnvironment(fn () => 'local');
});

it('runs the dev seeder only in the local environment when enabled', function () {
    config([
        'dev-seed.enabled' => true,
        'dev-seed.tenant_admin_email' => 'admin@ellr.local',
        'dev-seed.tenant_admin_password' => 'EllrDev!2026',
        'dev-seed.platform_enabled' => false,
        'dev-seed.tenant_timesheet_user_email' => 'timesheet@ellr.local',
        'dev-seed.tenant_timesheet_user_password' => 'EllrDev!2026',
    ]);

    $this->app->detectEnvironment(fn () => 'local');

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(2);
});

it('skips dev seeding outside the local environment', function () {
    config(['dev-seed.enabled' => true]);

    $this->app->detectEnvironment(fn () => 'production');

    (new DatabaseSeeder)->run();

    expect(User::query()->count())->toBe(0);
});

it('skips dev seeding when disabled in config', function () {
    config(['dev-seed.enabled' => false]);

    $this->app->detectEnvironment(fn () => 'local');

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(0);
});

it('does not invoke the dev seeder class directly when disabled', function () {
    config(['dev-seed.enabled' => false]);

    $this->seed(DevDatabaseSeeder::class);

    expect(User::query()->count())->toBe(0);
});
