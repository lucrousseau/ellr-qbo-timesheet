<?php

use App\Exceptions\QuickBooksOAuthException;
use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\OrganizationRealmService;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(OrganizationRealmService::class);

uses(RefreshDatabase::class);

it('binds a quickbooks realm to an organization on first connect', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-1']);

    app(OrganizationRealmService::class)->validateAndBindRealm($admin, $token);

    expect($admin->organization->fresh()->realm_id)->toBe('realm-1');
});

it('rejects connecting a second realm to the same organization', function () {
    $admin = User::factory()->admin()->create();
    $admin->organization->update(['realm_id' => 'realm-1']);
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-2']);

    try {
        app(OrganizationRealmService::class)->validateAndBindRealm($admin, $token);
        expect(false)->toBeTrue('Expected exception');
    } catch (QuickBooksOAuthException $exception) {
        expect($exception->reason)->toBe('realm_conflict');
    }
});

it('rejects connecting a realm already claimed by another organization', function () {
    $claimedOrganization = Organization::factory()->withRealm('realm-1')->create();
    User::factory()->admin()->create(['organization_id' => $claimedOrganization->id]);

    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-1']);

    try {
        app(OrganizationRealmService::class)->validateAndBindRealm($admin, $token);
        expect(false)->toBeTrue('Expected exception');
    } catch (QuickBooksOAuthException $exception) {
        expect($exception->reason)->toBe('realm_claimed');
    }
});

it('keeps the existing realm when reconnecting the same quickbooks company', function () {
    $admin = User::factory()->admin()->create();
    $admin->organization->update(['realm_id' => 'realm-1']);
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-1']);

    app(OrganizationRealmService::class)->validateAndBindRealm($admin, $token);

    expect($admin->organization->fresh()->realm_id)->toBe('realm-1');
});

it('rejects quickbooks connect when the administrator has no organization', function () {
    $admin = User::factory()->admin()->make(['organization_id' => null]);
    $admin->id = 1;
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-1', 'user_id' => $admin->id]);

    try {
        app(OrganizationRealmService::class)->validateAndBindRealm($admin, $token);
        expect(false)->toBeTrue('Expected exception');
    } catch (QuickBooksOAuthException $exception) {
        expect($exception->getMessage())->toBe('Organization is required before connecting QuickBooks.')
            ->and($exception->reason)->toBe('organization_missing');
    }
});

it('ignores realm release when no realm id is provided', function () {
    $admin = User::factory()->admin()->create();
    $admin->organization->update(['realm_id' => 'realm-1']);

    app(OrganizationRealmService::class)->releaseRealmWhenDisconnected($admin, null);
    app(OrganizationRealmService::class)->releaseRealmWhenDisconnected($admin, '');

    expect($admin->organization->fresh()->realm_id)->toBe('realm-1');
});

it('ignores realm release when the administrator has no organization', function () {
    $admin = User::factory()->admin()->make(['organization_id' => null]);
    $admin->id = 1;

    app(OrganizationRealmService::class)->releaseRealmWhenDisconnected($admin, 'realm-1');

    expect(true)->toBeTrue();
});

it('clears the organization realm when remaining tokens belong to other organizations', function () {
    $organization = Organization::factory()->withRealm('realm-1')->create();
    $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);
    $foreignOrganization = Organization::factory()->create();
    $foreignAdmin = User::factory()->admin()->create(['organization_id' => $foreignOrganization->id]);
    QuickBooksToken::factory()->forUser($foreignAdmin)->create(['realm_id' => 'realm-1']);

    app(OrganizationRealmService::class)->releaseRealmWhenDisconnected($admin, 'realm-1');

    expect($organization->fresh()->realm_id)->toBeNull();
});

it('ignores realm release when the disconnected realm does not match the organization', function () {
    $admin = User::factory()->admin()->create();
    $admin->organization->update(['realm_id' => 'realm-1']);

    app(OrganizationRealmService::class)->releaseRealmWhenDisconnected($admin, 'realm-2');

    expect($admin->organization->fresh()->realm_id)->toBe('realm-1');
});

it('clears the organization realm when the last quickbooks token is disconnected', function () {
    $admin = User::factory()->admin()->create();
    $admin->organization->update(['realm_id' => 'realm-1']);
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-1']);

    $token->delete();

    app(OrganizationRealmService::class)->releaseRealmWhenDisconnected($admin, 'realm-1');

    expect($admin->organization->fresh()->realm_id)->toBeNull();
});

it('keeps the organization realm when another token remains for the same realm', function () {
    $organization = Organization::factory()->withRealm('realm-1')->create();
    $adminA = User::factory()->admin()->create(['organization_id' => $organization->id]);
    $adminB = User::factory()->admin()->create(['organization_id' => $organization->id]);
    QuickBooksToken::factory()->forUser($adminA)->create(['realm_id' => 'realm-1']);
    QuickBooksToken::factory()->forUser($adminB)->create(['realm_id' => 'realm-1']);

    app(OrganizationRealmService::class)->releaseRealmWhenDisconnected($adminA, 'realm-1');

    expect($organization->fresh()->realm_id)->toBe('realm-1');
});
