<?php

use App\Models\User;
use Database\Seeders\DevDatabaseSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->app->detectEnvironment(fn () => 'local');
});

it('seeds dev admin and timesheet users with stable credentials', function () {
    config([
        'dev-seed.enabled' => true,
        'dev-seed.admin_email' => 'admin@ellr.local',
        'dev-seed.admin_password' => 'EllrDev!2026',
        'dev-seed.user_email' => 'timesheet@ellr.local',
        'dev-seed.user_password' => 'EllrDev!2026',
    ]);

    $this->seed(DevDatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@ellr.local')->firstOrFail();
    $timesheetUser = User::query()->where('email', 'timesheet@ellr.local')->firstOrFail();

    expect($admin->is_admin)->toBeTrue()
        ->and(Hash::check('EllrDev!2026', $admin->password))->toBeTrue()
        ->and($timesheetUser->is_admin)->toBeFalse()
        ->and(Hash::check('EllrDev!2026', $timesheetUser->password))->toBeTrue()
        ->and($admin->organization_id)->toBe($timesheetUser->organization_id)
        ->and($admin->organization)->not->toBeNull()
        ->and($admin->organization->slug)->toBe('ellr-dev');
});

it('refreshes passwords when the seeder runs again', function () {
    config([
        'dev-seed.enabled' => true,
        'dev-seed.admin_email' => 'admin@ellr.local',
        'dev-seed.admin_password' => 'EllrDev!2026',
        'dev-seed.user_email' => 'timesheet@ellr.local',
        'dev-seed.user_password' => 'EllrDev!2026',
    ]);

    User::factory()->admin()->create([
        'email' => 'admin@ellr.local',
        'password' => 'old-password',
    ]);

    $this->seed(DevDatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@ellr.local')->firstOrFail();

    expect(Hash::check('EllrDev!2026', $admin->password))->toBeTrue()
        ->and(Hash::check('old-password', $admin->password))->toBeFalse();
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
        'dev-seed.admin_email' => 'admin@ellr.local',
        'dev-seed.admin_password' => 'EllrDev!2026',
        'dev-seed.user_email' => 'timesheet@ellr.local',
        'dev-seed.user_password' => 'EllrDev!2026',
    ]);

    User::factory()->admin()->create([
        'email' => 'timesheet@ellr.local',
        'password' => 'EllrDev!2026',
    ]);

    $this->seed(DevDatabaseSeeder::class);

    $timesheetUser = User::query()->where('email', 'timesheet@ellr.local')->firstOrFail();

    expect($timesheetUser->is_admin)->toBeFalse();
});
