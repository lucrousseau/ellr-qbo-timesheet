<?php

use App\Enums\UserLevelCode;
use App\Models\User;
use App\Models\UserLevel;
use App\Support\UserLevelCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(UserLevel::class);
covers(UserLevelCode::class);

uses(RefreshDatabase::class);

it('defaults to the lowest employee level', function () {
    expect(UserLevelCode::default())->toBe(UserLevelCode::Employee)
        ->and(UserLevelCode::default()->value)->toBe('employee');
});

it('lists supported level codes', function () {
    expect(UserLevelCode::values())->toBe(['employee', 'lead', 'manager']);
});

it('seeds canonical user level rows from the catalog', function () {
    $expected = collect(UserLevelCatalog::definitions())
        ->sortBy('rank')
        ->pluck('code')
        ->all();

    expect(UserLevel::query()->orderBy('rank')->pluck('code')->all())->toBe($expected);
});

it('stores ranks only on user level rows', function () {
    $employee = UserLevel::query()->where('code', 'employee')->firstOrFail();
    $manager = UserLevel::query()->where('code', 'manager')->firstOrFail();

    expect($employee->isAtLeast($manager))->toBeFalse()
        ->and($manager->isAtLeast($employee))->toBeTrue();
});

it('returns null for unknown stored codes via tryCode', function () {
    $level = new UserLevel(['code' => 'unknown', 'rank' => 99]);

    expect($level->tryCode())->toBeNull();
});

it('returns the enum case for known stored codes via tryCode', function () {
    $level = UserLevel::query()->where('code', 'lead')->firstOrFail();

    expect($level->tryCode())->toBe(UserLevelCode::Lead);
});

it('treats equal ranks as satisfying isAtLeast', function () {
    $employee = UserLevel::query()->where('code', 'employee')->firstOrFail();

    expect($employee->isAtLeast($employee))->toBeTrue();
});

it('relates permission tiers to assigned users', function () {
    $user = User::factory()->create();
    $employee = UserLevel::query()->where('code', 'employee')->firstOrFail();

    expect($employee->users()->whereKey($user->id)->exists())->toBeTrue();
});
