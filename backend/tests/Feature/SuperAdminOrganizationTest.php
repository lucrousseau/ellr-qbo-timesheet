<?php

use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\User;
use App\Services\OrganizationRealmDataPurgeService;
use App\Services\OrganizationRealmService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('purges realm snapshots when the last organization token disconnects', function () {
    $admin = User::factory()->admin()->create();
    $admin->organization->update(['realm_id' => 'realm-1']);
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-1']);
    TimeActivitySnapshot::factory()->create(['realm_id' => 'realm-1', 'qbo_id' => '1']);

    $admin->quickBooksToken?->delete();

    $this->mock(OrganizationRealmDataPurgeService::class, function ($mock): void {
        $mock->shouldReceive('purgeRealmWhenUnclaimed')
            ->once()
            ->with('realm-1');
    });

    app(OrganizationRealmService::class)->releaseRealmWhenDisconnected($admin, 'realm-1');

    expect($admin->organization->fresh()->realm_id)->toBeNull();
});

it('lists organizations for platform super administrators', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Organization::factory()->create(['name' => 'Client A']);
    Organization::factory()->create(['name' => 'Client B']);

    $this->actingAs($superAdmin)
        ->getJson('/api/super-admin/organizations', frontendHeaders())
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('describes quickbooks connection state in organization listings', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Organization::factory()->withRealm('realm-connected')->create(['name' => 'Connected Org']);
    Organization::factory()->create(['name' => 'Disconnected Org', 'realm_id' => null]);

    $response = $this->actingAs($superAdmin)
        ->getJson('/api/super-admin/organizations', frontendHeaders())
        ->assertOk();

    $rows = collect($response->json('data'))->keyBy('name');

    expect($rows['Connected Org']['qbo_connected'])->toBeTrue()
        ->and($rows['Disconnected Org']['qbo_connected'])->toBeFalse();
});

it('rejects organization management for tenant administrators', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson('/api/super-admin/organizations', frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'super_admin_required');
});

it('rejects weak administrator passwords when creating client organizations', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->postJson('/api/super-admin/organizations', [
            'organization_name' => 'Weak Password LLC',
            'name' => 'Weak Admin',
            'email' => 'weak@client.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect(User::query()->where('email', 'weak@client.test')->exists())->toBeFalse();
});

it('creates client organizations for platform super administrators', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->postJson('/api/super-admin/organizations', [
            'organization_name' => 'Gamma LLC',
            'name' => 'Gamma Admin',
            'email' => 'gamma@client.test',
            'password' => validTestPassword(),
            'password_confirmation' => validTestPassword(),
        ], frontendHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Gamma LLC')
        ->assertJsonPath('data.users_count', 1);

    expect(User::query()->where('email', 'gamma@client.test')->exists())->toBeTrue();

    $admin = User::query()->where('email', 'gamma@client.test')->first();
    expect($admin->email_verified_at)->not->toBeNull();
});

it('updates client organization names for platform super administrators', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $organization = Organization::factory()->create(['name' => 'Before']);

    $this->actingAs($superAdmin)
        ->patchJson("/api/super-admin/organizations/{$organization->id}", [
            'name' => 'After',
        ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', 'After');
});

it('deletes client organizations for platform super administrators', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $organization = Organization::factory()->create();
    User::factory()->admin()->create(['organization_id' => $organization->id]);

    $this->actingAs($superAdmin)
        ->deleteJson("/api/super-admin/organizations/{$organization->id}", [], frontendHeaders())
        ->assertNoContent();

    expect(Organization::query()->whereKey($organization->id)->exists())->toBeFalse();
});

it('rejects deleting the super administrators own organization via api', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->deleteJson("/api/super-admin/organizations/{$superAdmin->organization_id}", [], frontendHeaders())
        ->assertStatus(409)
        ->assertJsonPath('error', 'cannot_delete_own_organization');
});

it('exposes is_super_admin on the authenticated user payload', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->getJson('/api/user', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.is_super_admin', true);
});

it('purges orphaned realm snapshots when deleting an organization', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $organization = Organization::factory()->withRealm('realm-purge')->create();
    User::factory()->admin()->create(['organization_id' => $organization->id]);
    TimeActivitySnapshot::factory()->create(['realm_id' => 'realm-purge', 'qbo_id' => '1']);

    $this->actingAs($superAdmin)
        ->deleteJson("/api/super-admin/organizations/{$organization->id}", [], frontendHeaders())
        ->assertNoContent();

    expect(TimeActivitySnapshot::query()->where('realm_id', 'realm-purge')->exists())->toBeFalse();
});
