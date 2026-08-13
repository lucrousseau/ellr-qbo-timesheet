<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Password;

covers(AuthController::class);
covers(PasswordResetController::class);

it('rejects registration with a weak password', function () {
    $this->postJson('/api/register', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('rejects password reset with a weak password', function () {
    $user = User::factory()->create(['password' => PasswordPolicy::validTestPassword()]);
    $token = Password::createToken($user);

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('registers with a password that satisfies the shared policy', function () {
    $password = PasswordPolicy::validTestPassword();

    $this->postJson('/api/register', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'policy@example.com',
        'password' => $password,
        'password_confirmation' => $password,
    ], frontendHeaders())
        ->assertCreated()
        ->assertJsonPath('user.email', 'policy@example.com');
});
