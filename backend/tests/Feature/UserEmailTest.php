<?php

use App\Http\Controllers\Api\UserEmailController;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

covers(UserEmailController::class);

it('changes the email for the signed-in user', function () {
    Notification::fake();

    $currentPassword = validTestPassword();
    $user = User::factory()->create([
        'email' => 'before@example.com',
        'password' => $currentPassword,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->patchJson('/api/user/email', [
            'current_password' => $currentPassword,
            'email' => 'after@example.com',
        ])
        ->assertOk()
        ->assertJsonPath(
            'message',
            'Email address updated. Check your inbox to verify the new address.',
        )
        ->assertJsonPath('user.email', 'after@example.com');

    $fresh = $user->fresh();

    expect($fresh->email)->toBe('after@example.com')
        ->and($fresh->email_verified_at)->toBeNull();

    Notification::assertSentTo($fresh, VerifyEmailNotification::class);
});

it('sends a verification email even when verification is not required to use the app', function () {
    Notification::fake();
    config(['app.require_email_verification' => false]);

    $currentPassword = validTestPassword();
    $user = User::factory()->create([
        'email' => 'before@example.com',
        'password' => $currentPassword,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->patchJson('/api/user/email', [
            'current_password' => $currentPassword,
            'email' => 'after@example.com',
        ])
        ->assertOk();

    Notification::assertSentTo($user->fresh(), VerifyEmailNotification::class);
});

it('rejects email change when the current password is wrong', function () {
    $user = User::factory()->create([
        'email' => 'before@example.com',
        'password' => validTestPassword(),
    ]);

    $this->actingAs($user)
        ->patchJson('/api/user/email', [
            'current_password' => 'WrongPassword!1',
            'email' => 'after@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);
});

it('rejects email change when the address is already taken', function () {
    $currentPassword = validTestPassword();
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create([
        'email' => 'before@example.com',
        'password' => $currentPassword,
    ]);

    $this->actingAs($user)
        ->patchJson('/api/user/email', [
            'current_password' => $currentPassword,
            'email' => 'taken@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects email change when the address is unchanged', function () {
    $currentPassword = validTestPassword();
    $user = User::factory()->create([
        'email' => 'same@example.com',
        'password' => $currentPassword,
    ]);

    $this->actingAs($user)
        ->patchJson('/api/user/email', [
            'current_password' => $currentPassword,
            'email' => 'same@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('requires authentication to change email', function () {
    $this->patchJson('/api/user/email', [
        'current_password' => validTestPassword(),
        'email' => 'after@example.com',
    ])->assertUnauthorized();
});
