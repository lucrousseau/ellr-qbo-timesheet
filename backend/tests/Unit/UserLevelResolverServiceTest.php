<?php

use App\Enums\UserLevelCode;
use App\Models\UserLevel;
use App\Services\UserLevelResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(UserLevelResolverService::class);

uses(RefreshDatabase::class);

it('resolves seeded level ids by code', function () {
    $resolver = app(UserLevelResolverService::class);

    $employeeId = UserLevel::query()->where('code', 'employee')->value('id');

    expect($resolver->idFor(UserLevelCode::Employee))->toBe((int) $employeeId)
        ->and($resolver->defaultId())->toBe((int) $employeeId)
        ->and($resolver->idFor(UserLevelCode::Employee))->toBeInt();
});

it('caches resolved level ids within the same resolver instance', function () {
    $resolver = app(UserLevelResolverService::class);

    $first = $resolver->idFor(UserLevelCode::Lead);
    UserLevel::query()->where('code', 'lead')->delete();

    expect($resolver->idFor(UserLevelCode::Lead))->toBe($first);
});

it('throws when a level code is not seeded', function () {
    UserLevel::query()->where('code', 'manager')->delete();

    expect(fn () => app(UserLevelResolverService::class)->idFor(UserLevelCode::Manager))
        ->toThrow(RuntimeException::class, 'User level [manager] is not seeded.');
});

it('resolves every seeded permission code to an integer id', function () {
    $resolver = app(UserLevelResolverService::class);

    foreach (UserLevelCode::cases() as $code) {
        $expectedId = UserLevel::query()->where('code', $code->value)->value('id');

        expect($resolver->idFor($code))->toBe((int) $expectedId)->toBeInt();
    }
});
