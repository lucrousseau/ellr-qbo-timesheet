<?php

use App\Http\Controllers\Api\UserPasswordController;
use App\Models\Organization;
use App\Models\User;
use App\Services\UserLevelResolverService;
use App\Support\OrganizationApiResponse;
use App\Support\UserApiResponse;
use Illuminate\Support\Facades\Hash;

covers(UserPasswordController::class);
covers(UserApiResponse::class);
covers(OrganizationApiResponse::class);
covers(UserLevelResolverService::class);

it('exposes is_admin in api user payloads', function () {
    $user = User::factory()->admin()->create();

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload)->toHaveKey('is_admin')
        ->and($payload['is_admin'])->toBeTrue();
});

it('exposes is_admin false for non-administrator users', function () {
    $user = User::factory()->create();

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload['is_admin'])->toBeFalse();
});

it('exposes the default permission level in api user payloads', function () {
    $user = User::factory()->create();

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload)->toHaveKey('level')
        ->and($payload['level'])->toBe('employee');
});

it('defaults level for non-persisted users in api payloads', function () {
    $user = User::factory()->make(['email' => 'draft@example.com']);

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload['level'])->toBe('employee');
});

it('exposes is_super_admin in api user payloads', function () {
    $user = User::factory()->superAdmin()->create();

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload)->toHaveKey('is_super_admin')
        ->and($payload['is_super_admin'])->toBeBool()->toBeTrue();
});

it('exposes is_super_admin false for non-super-admin users', function () {
    $user = User::factory()->create(['is_super_admin' => 0]);

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload['is_super_admin'])->toBeBool()->toBeFalse();
});

it('hides user_level_id from api user payloads', function () {
    $user = User::factory()->create();

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload)->not->toHaveKey('user_level_id');
});

it('throws when a persisted user is missing a user level relation', function () {
    $user = User::factory()->create();
    $user->unsetRelation('userLevel');
    $user->setRelation('userLevel', null);

    expect(fn () => UserApiResponse::resource($user)->toArray())
        ->toThrow(LogicException::class, 'Persisted user is missing a user level relation.');
});

it('preserves core user attributes in api payloads', function () {
    $user = User::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload['name'])->toBe('Jane Doe')
        ->and($payload['email'])->toBe('jane@example.com');
});

it('loads the organization relation when building api payloads', function () {
    $user = User::factory()->create();
    $user->unsetRelation('organization');

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload['organization']['id'])->toBe($user->organization_id)
        ->and($user->relationLoaded('organization'))->toBeTrue();
});

it('loads the user level relation when building api payloads', function () {
    $user = User::factory()->create();
    $user->unsetRelation('userLevel');

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload['level'])->toBe('employee')
        ->and($user->relationLoaded('userLevel'))->toBeTrue();
});

it('exposes organization in api user payloads', function () {
    $organization = Organization::factory()->withRealm('realm-42')->create([
        'name' => 'Acme Consulting',
        'slug' => 'acme-consulting',
    ]);
    $user = User::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload)->not->toHaveKey('organization_id')
        ->and($payload['organization'])->toBe([
            'id' => $organization->id,
            'name' => 'Acme Consulting',
            'slug' => 'acme-consulting',
            'qbo_connected' => true,
            'company_timezone' => null,
        ]);
});

it('reports qbo_connected false when the organization has no realm', function () {
    $organization = Organization::factory()->create(['realm_id' => null]);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload['organization']['qbo_connected'])->toBeFalse();
});

it('returns a null organization when the user has no tenant', function () {
    $user = User::factory()->make([
        'organization_id' => null,
        'email' => 'orphan@example.com',
    ]);
    $user->id = 1;

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload)->not->toHaveKey('organization_id')
        ->and($payload['organization'])->toBeNull();
});

it('exposes supervisor and review metadata in api user payloads', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'timezone' => 'America/Toronto',
    ]);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'supervisor_id' => $supervisor->id,
        'timezone' => 'America/New_York',
    ]);

    $payload = UserApiResponse::resource($employee)->toArray();

    expect($payload['supervisor_id'])->toBe($supervisor->id)
        ->and($payload['can_review_time_entries'])->toBeFalse()
        ->and($payload['timezone'])->toBe('America/New_York')
        ->and($payload['effective_display_timezone'])->toBeString();
});

it('marks supervisors who can review time entries', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->create(['organization_id' => $admin->organization_id]);
    User::factory()->create([
        'organization_id' => $admin->organization_id,
        'supervisor_id' => $supervisor->id,
    ]);

    $payload = UserApiResponse::resource($supervisor->fresh())->toArray();

    expect($payload['can_review_time_entries'])->toBeTrue();
});

it('returns is_admin on the authenticated user endpoint', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('user.is_admin', true);
});

it('returns is_admin false for non-admin users', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('user.is_admin', false);
});

it('changes the password for the signed-in user', function () {
    $currentPassword = validTestPassword();
    $newPassword = validTestPasswordAlt();
    $user = User::factory()->create(['password' => $currentPassword]);

    $this->actingAs($user)
        ->patchJson('/api/user/password', [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Password updated successfully.');

    expect(Hash::check($newPassword, $user->fresh()->password))->toBeTrue();
});

it('rotates the remember token when the password changes', function () {
    $currentPassword = validTestPassword();
    $newPassword = validTestPasswordAlt();
    $user = User::factory()->create([
        'password' => $currentPassword,
        'remember_token' => str_repeat('a', 60),
    ]);

    $this->actingAs($user)
        ->patchJson('/api/user/password', [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
        ->assertOk();

    $rememberToken = $user->fresh()->remember_token;

    expect($rememberToken)->not->toBe(str_repeat('a', 60))
        ->and(strlen((string) $rememberToken))->toBe(60);
});

it('rejects password change when the current password is wrong', function () {
    $user = User::factory()->create(['password' => validTestPassword()]);

    $this->actingAs($user)
        ->patchJson('/api/user/password', [
            'current_password' => 'WrongPassword!1',
            'password' => validTestPasswordAlt(),
            'password_confirmation' => validTestPasswordAlt(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);
});

it('rejects password change when the new password is too weak', function () {
    $currentPassword = validTestPassword();
    $user = User::factory()->create(['password' => $currentPassword]);

    $this->actingAs($user)
        ->patchJson('/api/user/password', [
            'current_password' => $currentPassword,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('requires authentication to change password', function () {
    $this->patchJson('/api/user/password', [
        'current_password' => validTestPassword(),
        'password' => validTestPasswordAlt(),
        'password_confirmation' => validTestPasswordAlt(),
    ])->assertUnauthorized();
});
