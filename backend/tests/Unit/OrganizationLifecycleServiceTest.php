<?php

use App\Exceptions\OrganizationLifecycleException;
use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\User;
use App\Services\AuthSessionService;
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

it('lists organizations ordered by name', function () {
    Organization::factory()->create(['name' => 'Zulu Corp']);
    Organization::factory()->create(['name' => 'Alpha Corp']);

    $listed = app(OrganizationLifecycleService::class)->listOrganizations();

    expect($listed->pluck('name')->all())->toBe(['Alpha Corp', 'Zulu Corp']);
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

it('invalidates user sessions when deleting an organization', function () {
    $actor = User::factory()->superAdmin()->create();
    $organization = Organization::factory()->create();
    $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);

    $this->mock(AuthSessionService::class, function ($mock) use ($admin): void {
        $mock->shouldReceive('invalidateUserSessions')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->is($admin)));
    });

    $this->mock(QuickBooksService::class);

    app(OrganizationLifecycleService::class)->deleteOrganization($actor, $organization);
});

it('invalidates sessions for every user in the deleted organization', function () {
    $actor = User::factory()->superAdmin()->create();
    $organization = Organization::factory()->create();
    $first = User::factory()->create(['organization_id' => $organization->id]);
    $second = User::factory()->create(['organization_id' => $organization->id]);
    $invalidatedUserIds = [];

    $this->mock(AuthSessionService::class, function ($mock) use (&$invalidatedUserIds): void {
        $mock->shouldReceive('invalidateUserSessions')
            ->twice()
            ->andReturnUsing(function (User $user) use (&$invalidatedUserIds): void {
                $invalidatedUserIds[] = $user->id;
            });
    });

    $this->mock(QuickBooksService::class);

    app(OrganizationLifecycleService::class)->deleteOrganization($actor, $organization->fresh());

    expect($invalidatedUserIds)->toEqualCanonicalizing([$first->id, $second->id]);
});

it('disconnects quickbooks before deleting tenant users', function () {
    $actor = User::factory()->superAdmin()->create();
    $organization = Organization::factory()->create();
    $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);
    $token = QuickBooksToken::factory()->forUser($admin)->create();

    $this->mock(QuickBooksService::class, function ($mock): void {
        $mock->shouldReceive('disconnect')->once();
    });

    app(OrganizationLifecycleService::class)->deleteOrganization($actor, $organization);

    expect(QuickBooksToken::query()->whereKey($token->id)->exists())->toBeFalse();
});

it('deletes stored quickbooks tokens when disconnect throws', function () {
    $actor = User::factory()->superAdmin()->create();
    $organization = Organization::factory()->create();
    $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);
    $token = QuickBooksToken::factory()->forUser($admin)->create();

    $this->mock(QuickBooksService::class, function ($mock): void {
        $mock->shouldReceive('disconnect')->once()->andThrow(new RuntimeException('offline'));
    });

    app(OrganizationLifecycleService::class)->deleteOrganization($actor, $organization);

    expect(QuickBooksToken::query()->whereKey($token->id)->exists())->toBeFalse()
        ->and(Organization::query()->whereKey($organization->id)->exists())->toBeFalse();
});

it('allows deleting an organization when another platform super administrator remains', function () {
    $actor = User::factory()->superAdmin()->create();
    $target = Organization::factory()->create();
    User::factory()->superAdmin()->create(['organization_id' => $target->id]);

    $this->mock(QuickBooksService::class);

    app(OrganizationLifecycleService::class)->deleteOrganization($actor, $target);

    expect(Organization::query()->whereKey($target->id)->exists())->toBeFalse();
});

it('rejects deleting the super administrators own organization', function () {
    $organization = Organization::factory()->create();
    $actor = User::factory()->superAdmin()->create(['organization_id' => $organization->id]);

    expect(fn () => app(OrganizationLifecycleService::class)->deleteOrganization($actor, $organization))
        ->toThrow(OrganizationLifecycleException::class, 'You cannot delete the organization that owns your account.');
});

it('allows deleting any tenant when the platform operator has no organization', function () {
    $actor = User::factory()->superAdmin()->create();
    $organization = Organization::factory()->create();
    User::factory()->admin()->create(['organization_id' => $organization->id]);

    $this->mock(QuickBooksService::class);

    app(OrganizationLifecycleService::class)->deleteOrganization($actor, $organization);

    expect($actor->fresh()->organization_id)->toBeNull()
        ->and(Organization::query()->whereKey($organization->id)->exists())->toBeFalse();
});

it('rejects deleting the last super administrator organization', function () {
    $target = Organization::factory()->create();
    User::factory()->superAdmin()->create(['organization_id' => $target->id]);

    $actor = User::factory()->admin()->create();

    expect(fn () => app(OrganizationLifecycleService::class)->deleteOrganization($actor, $target))
        ->toThrow(OrganizationLifecycleException::class, 'Deleting this organization would remove the last platform super administrator.');
});
