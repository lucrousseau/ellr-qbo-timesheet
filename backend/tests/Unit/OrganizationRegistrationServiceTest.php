<?php

use App\Models\Organization;
use App\Services\OrganizationRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

covers(OrganizationRegistrationService::class);

uses(RefreshDatabase::class);

it('creates an organization and founding administrator on registration', function () {
    $user = app(OrganizationRegistrationService::class)->registerAdministrator([
        'first_name' => 'Jane',
        'last_name' => 'Founder',
        'organization_name' => 'Jane Consulting',
        'email' => 'jane@example.com',
        'password' => validTestPassword(),
    ]);

    expect($user->isAdmin())->toBeTrue()
        ->and($user->organization)->not->toBeNull()
        ->and($user->relationLoaded('organization'))->toBeTrue()
        ->and($user->organization->name)->toBe('Jane Consulting')
        ->and(Organization::query()->count())->toBe(1);
});

it('derives an organization name from the administrator name when omitted', function () {
    $user = app(OrganizationRegistrationService::class)->registerAdministrator([
        'first_name' => 'Jane',
        'last_name' => 'Founder',
        'email' => 'jane@example.com',
        'password' => validTestPassword(),
    ]);

    expect($user->organization->name)->toBe('Jane Founder organization');
});

it('treats whitespace-only organization names as omitted', function () {
    $user = app(OrganizationRegistrationService::class)->registerAdministrator([
        'first_name' => 'Jane',
        'last_name' => 'Founder',
        'organization_name' => '   ',
        'email' => 'whitespace@example.com',
        'password' => validTestPassword(),
    ]);

    expect($user->organization->name)->toBe('Jane Founder organization');
});

it('trims organization names before persisting them', function () {
    $user = app(OrganizationRegistrationService::class)->registerAdministrator([
        'first_name' => 'Jane',
        'last_name' => 'Founder',
        'organization_name' => '  Acme Inc  ',
        'email' => 'trim@example.com',
        'password' => validTestPassword(),
    ]);

    expect($user->organization->name)->toBe('Acme Inc');
});

it('casts non-string organization names before persisting them', function () {
    $user = app(OrganizationRegistrationService::class)->registerAdministrator([
        'first_name' => 'Jane',
        'last_name' => 'Founder',
        'organization_name' => 12_345,
        'email' => 'numeric-org@example.com',
        'password' => validTestPassword(),
    ]);

    expect($user->organization->name)->toBe('12345');
});

it('retries slug generation when a generated slug already exists', function () {
    Organization::factory()->create(['slug' => 'acme-inc-dupref']);
    $attempts = 0;
    Str::createRandomStringsUsing(function () use (&$attempts): string {
        return $attempts++ === 0 ? 'dupref' : 'unique1';
    });

    $user = app(OrganizationRegistrationService::class)->registerAdministrator([
        'first_name' => 'Acme',
        'last_name' => 'Admin',
        'organization_name' => 'Acme Inc',
        'email' => 'slug-retry@example.com',
        'password' => validTestPassword(),
    ]);

    Str::createRandomStringsUsing(null);

    expect($user->organization->slug)->toBe('acme-inc-unique1');
});

it('uses a fallback slug when the organization name cannot be slugified', function () {
    $user = app(OrganizationRegistrationService::class)->registerAdministrator([
        'first_name' => '!!!',
        'last_name' => '',
        'email' => 'slug@example.com',
        'password' => validTestPassword(),
    ]);

    expect($user->organization->slug)->toStartWith('organization-');
});

it('generates unique organization slugs for registrations with the same display name', function () {
    $first = app(OrganizationRegistrationService::class)->registerAdministrator([
        'first_name' => 'Acme',
        'last_name' => 'Admin',
        'organization_name' => 'Acme Inc',
        'email' => 'first@example.com',
        'password' => validTestPassword(),
    ]);
    $second = app(OrganizationRegistrationService::class)->registerAdministrator([
        'first_name' => 'Acme',
        'last_name' => 'Admin Two',
        'organization_name' => 'Acme Inc',
        'email' => 'second@example.com',
        'password' => validTestPassword(),
    ]);

    expect($first->organization->slug)->not->toBe($second->organization->slug)
        ->and($first->organization->slug)->toStartWith('acme-inc-')
        ->and($second->organization->slug)->toStartWith('acme-inc-');
});
