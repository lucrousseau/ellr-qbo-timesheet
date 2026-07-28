<?php

use App\Http\Controllers\Api\UserPasswordController;
use App\Models\User;
use App\Support\UserApiResponse;
use Illuminate\Support\Facades\Hash;

covers(UserPasswordController::class);
covers(UserApiResponse::class);

it('exposes is_admin in api user payloads', function () {
    $user = User::factory()->admin()->create();

    $payload = UserApiResponse::resource($user)->toArray();

    expect($payload)->toHaveKey('is_admin')
        ->and($payload['is_admin'])->toBeTrue();
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
