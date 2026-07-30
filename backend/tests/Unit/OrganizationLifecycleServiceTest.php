<?php

use App\Exceptions\OrganizationLifecycleException;
use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\User;
use App\Services\OrganizationLifecycleService;
use App\Services\QuickBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

covers(OrganizationLifecycleService::class);

it('lists organizations with user counts', function () {
    $organization = Organization::factory()->create(['name' => 'Acme']);
    User::factory()->count(2)->create(['organization_id' => $organization->id]);

    $listed = app(OrganizationLifecycleService::class)->listOrganizations();

    expect($listed)->toHaveCount(1)
        ->and($listed->first()->users_count)->toBe(2);
});

it('creates an organization with a verified founding administrator', function () {
    $organization = app(OrganizationLifecycleService::class)->createOrganization([
        'organization_name' => 'Beta Corp',
        'name' => 'Beta Admin',
        'email' => 'admin@beta.test',
        'password' => validTestPassword(),
        'password_confirmation' => validTestPassword(),
    ]);

    $admin = User::query()->where('email', 'admin@beta.test')->first();

    expect($organization->name)->toBe('Beta Corp')
        ->and($admin)->not->toBeNull()
        ->and($admin->isAdmin())->toBeTrue()
        ->and($admin->organization_id)->toBe($organization->id)
        ->and($admin->email_verified_at)->not->toBeNull();
});

it('updates organization names', function () {
    $organization = Organization::factory()->create(['name' => 'Old Name']);

    $updated = app(OrganizationLifecycleService::class)->updateOrganization($organization, [
        'name' => 'New Name',
    ]);

    expect($updated->name)->toBe('New Name');
});

it('deletes an organization and purges realm snapshots', function () {
    $actor = User::factory()->superAdmin()->create();
    $organization = Organization::factory()->withRealm('realm-delete')->create();
    $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-delete']);
    TimeActivitySnapshot::factory()->create(['realm_id' => 'realm-delete', 'qbo_id' => '1']);

    $this->mock(QuickBooksService::class, function ($mock): void {
        $mock->shouldReceive('disconnect')->once();
    });

    app(OrganizationLifecycleService::class)->deleteOrganization($actor, $organization);

    expect(Organization::query()->whereKey($organization->id)->exists())->toBeFalse()
        ->and(User::query()->where('organization_id', $organization->id)->exists())->toBeFalse()
        ->and(TimeActivitySnapshot::query()->where('realm_id', 'realm-delete')->exists())->toBeFalse();
});

it('rejects deleting the super administrators own organization', function () {
    $actor = User::factory()->superAdmin()->create();
    $organization = $actor->organization;

    expect(fn () => app(OrganizationLifecycleService::class)->deleteOrganization($actor, $organization))
        ->toThrow(OrganizationLifecycleException::class, 'You cannot delete the organization that owns your account.');
});

it('rejects deleting the last super administrator organization', function () {
    $target = Organization::factory()->create();
    User::factory()->superAdmin()->create(['organization_id' => $target->id]);

    $actor = User::factory()->admin()->create();

    expect(fn () => app(OrganizationLifecycleService::class)->deleteOrganization($actor, $target))
        ->toThrow(OrganizationLifecycleException::class, 'Deleting this organization would remove the last platform super administrator.');
});
