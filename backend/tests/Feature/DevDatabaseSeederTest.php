<?php

use App\Models\User;
use Database\Seeders\DevDatabaseSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->app->detectEnvironment(fn () => 'local');
});

it('seeds tenant admin, platform operator, and timesheet users with stable credentials', function () {
    config([
        'dev-seed.enabled' => true,
        'dev-seed.tenant_admin_email' => 'admin@ellr.local',
        'dev-seed.tenant_admin_password' => 'EllrDev!2026',
        'dev-seed.platform_email' => 'platform@ellr.local',
        'dev-seed.platform_password' => 'EllrDev!2026',
        'dev-seed.tenant_timesheet_user_email' => 'timesheet@ellr.local',
        'dev-seed.tenant_timesheet_user_password' => 'EllrDev!2026',
    ]);

    $this->seed(DevDatabaseSeeder::class);

    $tenantAdmin = User::query()->where('email', 'admin@ellr.local')->firstOrFail();
    $platformOperator = User::query()->where('email', 'platform@ellr.local')->firstOrFail();
    $timesheetUser = User::query()->where('email', 'timesheet@ellr.local')->firstOrFail();

    expect($tenantAdmin->is_admin)->toBeTrue()
        ->and($tenantAdmin->is_super_admin)->toBeFalse()
        ->and($platformOperator->is_admin)->toBeFalse()
        ->and($platformOperator->is_super_admin)->toBeTrue()
        ->and(Hash::check('EllrDev!2026', $tenantAdmin->password))->toBeTrue()
        ->and(Hash::check('EllrDev!2026', $platformOperator->password))->toBeTrue()
        ->and($timesheetUser->is_admin)->toBeFalse()
        ->and($timesheetUser->is_super_admin)->toBeFalse()
        ->and(Hash::check('EllrDev!2026', $timesheetUser->password))->toBeTrue()
        ->and($tenantAdmin->organization_id)->toBe($timesheetUser->organization_id)
        ->and($platformOperator->organization_id)->toBe($tenantAdmin->organization_id)
        ->and($tenantAdmin->organization)->not->toBeNull()
        ->and($tenantAdmin->organization->slug)->toBe('ellr-dev');
});

it('does not seed a platform operator when platform seeding is disabled', function () {
    config([
        'dev-seed.enabled' => true,
        'dev-seed.platform_enabled' => false,
        'dev-seed.tenant_admin_email' => 'admin@ellr.local',
        'dev-seed.tenant_admin_password' => 'EllrDev!2026',
        'dev-seed.platform_email' => 'platform@ellr.local',
        'dev-seed.platform_password' => 'EllrDev!2026',
        'dev-seed.tenant_timesheet_user_email' => 'timesheet@ellr.local',
        'dev-seed.tenant_timesheet_user_password' => 'EllrDev!2026',
    ]);

    $this->seed(DevDatabaseSeeder::class);

    $tenantAdmin = User::query()->where('email', 'admin@ellr.local')->firstOrFail();

    expect($tenantAdmin->is_admin)->toBeTrue()
        ->and($tenantAdmin->is_super_admin)->toBeFalse()
        ->and(User::query()->where('email', 'platform@ellr.local')->exists())->toBeFalse();
});

it('clears super admin privileges from the tenant admin on reseed', function () {
    config([
        'dev-seed.enabled' => true,
        'dev-seed.tenant_admin_email' => 'admin@ellr.local',
        'dev-seed.tenant_admin_password' => 'EllrDev!2026',
        'dev-seed.platform_email' => 'platform@ellr.local',
        'dev-seed.platform_password' => 'EllrDev!2026',
        'dev-seed.tenant_timesheet_user_email' => 'timesheet@ellr.local',
        'dev-seed.tenant_timesheet_user_password' => 'EllrDev!2026',
    ]);

    User::factory()->superAdmin()->create([
        'email' => 'admin@ellr.local',
        'password' => 'EllrDev!2026',
    ]);

    $this->seed(DevDatabaseSeeder::class);

    $tenantAdmin = User::query()->where('email', 'admin@ellr.local')->firstOrFail();

    expect($tenantAdmin->is_admin)->toBeTrue()
        ->and($tenantAdmin->is_super_admin)->toBeFalse();
});

it('refreshes passwords when the seeder runs again', function () {
    config([
        'dev-seed.enabled' => true,
        'dev-seed.tenant_admin_email' => 'admin@ellr.local',
        'dev-seed.tenant_admin_password' => 'EllrDev!2026',
        'dev-seed.platform_email' => 'platform@ellr.local',
        'dev-seed.platform_password' => 'EllrDev!2026',
        'dev-seed.tenant_timesheet_user_email' => 'timesheet@ellr.local',
        'dev-seed.tenant_timesheet_user_password' => 'EllrDev!2026',
    ]);

    User::factory()->admin()->create([
        'email' => 'admin@ellr.local',
        'password' => 'old-password',
    ]);

    $this->seed(DevDatabaseSeeder::class);

    $tenantAdmin = User::query()->where('email', 'admin@ellr.local')->firstOrFail();

    expect(Hash::check('EllrDev!2026', $tenantAdmin->password))->toBeTrue()
        ->and(Hash::check('old-password', $tenantAdmin->password))->toBeFalse();
});

it('does not seed outside the local environment', function () {
    config(['dev-seed.enabled' => true]);

    $this->app->detectEnvironment(fn () => 'production');

    (new DevDatabaseSeeder)->run();

    expect(User::query()->count())->toBe(0);
});

it('does not seed when dev seeding is disabled', function () {
    config(['dev-seed.enabled' => false]);

    $this->seed(DevDatabaseSeeder::class);

    expect(User::query()->count())->toBe(0);
});

it('clears admin privileges from the timesheet dev account', function () {
    config([
        'dev-seed.enabled' => true,
        'dev-seed.tenant_admin_email' => 'admin@ellr.local',
        'dev-seed.tenant_admin_password' => 'EllrDev!2026',
        'dev-seed.platform_email' => 'platform@ellr.local',
        'dev-seed.platform_password' => 'EllrDev!2026',
        'dev-seed.tenant_timesheet_user_email' => 'timesheet@ellr.local',
        'dev-seed.tenant_timesheet_user_password' => 'EllrDev!2026',
    ]);

    User::factory()->admin()->create([
        'email' => 'timesheet@ellr.local',
        'password' => 'EllrDev!2026',
    ]);

    $this->seed(DevDatabaseSeeder::class);

    $timesheetUser = User::query()->where('email', 'timesheet@ellr.local')->firstOrFail();

    expect($timesheetUser->is_admin)->toBeFalse()
        ->and($timesheetUser->is_super_admin)->toBeFalse();
});
