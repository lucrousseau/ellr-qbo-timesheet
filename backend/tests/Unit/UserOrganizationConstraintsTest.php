<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

covers(User::class);

uses(RefreshDatabase::class);

it('requires organization_id on persisted users', function () {
    expect(Schema::getColumnType('users', 'organization_id'))->toBe('integer');

    expect(fn () => User::query()->create([
        'name' => 'Orphan User',
        'email' => 'orphan@example.com',
        'password' => validTestPassword(),
    ]))->toThrow(QueryException::class);
});

it('casts is_admin database values to booleans', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_admin' => 1])->save();

    expect($user->fresh()->isAdmin())->toBeTrue()
        ->and($user->fresh()->getAttribute('is_admin'))->toBeBool();
});

it('casts disabled administrator flags to false booleans', function () {
    $user = User::factory()->admin()->create();
    $user->forceFill(['is_admin' => 0])->save();

    expect($user->fresh()->isAdmin())->toBeFalse()
        ->and($user->fresh()->getAttribute('is_admin'))->toBeBool();
});

it('casts quickbooks customer access flags to booleans', function () {
    $user = User::factory()->create();
    $user->forceFill(['qbo_all_customers_access' => 1])->save();

    expect($user->fresh()->getAttribute('qbo_all_customers_access'))->toBeBool()
        ->and($user->fresh()->qbo_all_customers_access)->toBeTrue();
});

it('returns supported locales from preferredLocale', function () {
    $frenchUser = User::factory()->create(['locale' => 'fr']);
    $englishUser = User::factory()->create(['locale' => 'en']);

    expect($frenchUser->preferredLocale())->toBe('fr')
        ->and($englishUser->preferredLocale())->toBe('en');
});

it('falls back to english for unsupported locales', function () {
    $user = User::factory()->create(['locale' => 'de']);

    expect($user->preferredLocale())->toBe('en');
});

it('resolves route bindings within the authenticated users organization', function () {
    $admin = User::factory()->admin()->create();
    $foreignUser = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    $this->actingAs($admin);

    expect(fn () => $foreignUser->resolveRouteBinding($foreignUser->getKey()))
        ->toThrow(ModelNotFoundException::class);
});

it('resolves route bindings for users in the same organization', function () {
    $admin = User::factory()->admin()->create();
    $timesheetUser = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    $this->actingAs($admin);

    $resolved = $timesheetUser->resolveRouteBinding($timesheetUser->getKey());

    expect($resolved->is($timesheetUser))->toBeTrue();
});

it('resolves route bindings using a custom route key field', function () {
    $admin = User::factory()->admin()->create();
    $timesheetUser = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    $this->actingAs($admin);

    $resolved = $timesheetUser->resolveRouteBinding('7', 'qbo_employee_ref');

    expect($resolved->is($timesheetUser))->toBeTrue();
});

it('returns not found when resolving route bindings without an authenticated actor', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    expect(fn () => $user->resolveRouteBinding($user->getKey()))
        ->toThrow(NotFoundHttpException::class);
});
