<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

covers(User::class);

uses(RefreshDatabase::class);

it('returns the latest quickbooks token for the user', function () {
    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'old-realm']);
    $latest = QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'new-realm']);

    expect($user->quickBooksToken->is($latest))->toBeTrue()
        ->and($user->quickBooksToken->realm_id)->toBe('new-realm');
});

it('returns null when the user has no quickbooks token', function () {
    $user = User::factory()->create();

    expect($user->quickBooksToken)->toBeNull();
});

it('hides sensitive attributes from array conversion', function () {
    $user = User::factory()->admin()->create(['password' => 'secret-password']);

    $array = $user->toArray();

    expect($array)->not->toHaveKey('password')
        ->and($array)->not->toHaveKey('remember_token')
        ->and($array)->not->toHaveKey('is_admin');
});

it('casts is_admin to a boolean', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->is_admin)->toBeBool()->and($admin->is_admin)->toBeTrue();
});

it('casts password and email verification datetime', function () {
    $user = User::factory()->create([
        'password' => 'plain-password',
        'email_verified_at' => '2026-01-01 00:00:00',
    ]);

    expect($user->password)->not->toBe('plain-password')
        ->and($user->email_verified_at)->toBeInstanceOf(Carbon::class);
});

it('identifies administrator accounts', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    expect($admin->isAdmin())->toBeTrue()
        ->and($user->isAdmin())->toBeFalse();
});

it('returns the stored locale from preferredLocale', function () {
    $user = User::factory()->create(['locale' => 'fr']);

    expect($user->preferredLocale())->toBe('fr');
});

it('defaults preferredLocale to english when locale is missing', function () {
    $user = User::factory()->make(['locale' => null]);

    expect($user->preferredLocale())->toBe('en');
});

it('defaults preferredLocale to english for unsupported values', function () {
    $user = User::factory()->create(['locale' => 'de']);

    expect($user->preferredLocale())->toBe('en');
});
