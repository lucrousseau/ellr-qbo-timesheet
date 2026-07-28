<?php

use App\Models\User;
use App\Support\UserLocaleApplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

uses(RefreshDatabase::class);

covers(UserLocaleApplicator::class);

it('applies the preferred locale for a known user', function () {
    $user = User::factory()->make(['locale' => 'fr']);

    UserLocaleApplicator::applyForUser($user);

    expect(App::getLocale())->toBe('fr');
});

it('looks up a user by email and applies their locale', function () {
    User::factory()->create(['email' => 'jane@example.com', 'locale' => 'fr']);

    UserLocaleApplicator::applyForEmail('jane@example.com');

    expect(App::getLocale())->toBe('fr');
});

it('keeps the default locale when the email is unknown', function () {
    App::setLocale('en');

    UserLocaleApplicator::applyForEmail('missing@example.com');

    expect(App::getLocale())->toBe('en');
});
