<?php

use App\Models\Organization;
use App\Models\User;
use App\Support\SuperAdminOrganizationApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serializes organization rows for platform operator dashboards', function () {
    $organization = Organization::factory()->withRealm('realm-42')->create(['name' => 'Acme Corp']);
    $organization->users_count = 3;
    User::factory()->admin()->create([
        'organization_id' => $organization->id,
        'first_name' => 'Ada',
        'last_name' => 'Admin',
        'email' => 'ada@acme.test',
    ]);

    expect(SuperAdminOrganizationApiResponse::resource($organization))->toMatchArray([
        'id' => $organization->id,
        'name' => 'Acme Corp',
        'slug' => $organization->slug,
        'qbo_connected' => true,
        'users_count' => 3,
        'created_at' => $organization->created_at?->toIso8601String(),
        'administrator' => [
            'first_name' => 'Ada',
            'last_name' => 'Admin',
            'email' => 'ada@acme.test',
        ],
    ]);
});

it('counts users when the aggregate is not already loaded', function () {
    $organization = Organization::factory()->create(['name' => 'Beta LLC']);
    User::factory()->count(2)->create(['organization_id' => $organization->id]);

    expect(SuperAdminOrganizationApiResponse::resource($organization)['users_count'])->toBe(2);
});

it('returns a null administrator when the tenant has no admin account', function () {
    $organization = Organization::factory()->create(['name' => 'Empty Org']);

    expect(SuperAdminOrganizationApiResponse::resource($organization)['administrator'])->toBeNull();
});
