<?php

use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

covers(EmailVerificationController::class);
covers(PasswordResetController::class);

it('verifies an email from a signed link', function () {
    config(['app.frontend_auth_url' => 'http://localhost:5174']);

    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ],
    );

    $this->get($verificationUrl)
        ->assertRedirect('http://localhost:5174?email=verified');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects expired email verification links', function () {
    config(['app.frontend_auth_url' => 'http://localhost:5174']);

    $user = User::factory()->unverified()->create();
    $now = now();

    Carbon::setTestNow($now);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        $now->copy()->addHour(),
        [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ],
    );

    Carbon::setTestNow($now->copy()->addHours(2));

    $this->withoutMiddleware(ValidateSignature::class)
        ->get($verificationUrl)
        ->assertRedirect('http://localhost:5174?email=error&reason=expired');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    Carbon::setTestNow();
});

it('redirects already verified users without changing verification state', function () {
    config(['app.frontend_auth_url' => 'http://localhost:5174']);

    $user = User::factory()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ],
    );

    $this->get($verificationUrl)
        ->assertRedirect('http://localhost:5174?email=already_verified');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('returns a message when a verified user requests another verification email', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/email/verification-notification', [], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('message', 'Email already verified.');

    Notification::assertNothingSent();
});

it('rejects invalid email verification links', function () {
    config(['app.frontend_auth_url' => 'http://localhost:5174/']);

    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        [
            'id' => $user->id,
            'hash' => 'invalid-hash',
        ],
    );

    $this->get($verificationUrl)
        ->assertRedirect('http://localhost:5174?email=error&reason=invalid');
});

it('sends a verification email for authenticated unverified users', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->postJson('/api/email/verification-notification', [], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('message', 'Verification link sent.');

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('rejects login when email verification is required and pending', function () {
    config(['app.require_email_verification' => true]);

    $user = User::factory()->unverified()->create(['password' => 'password']);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ], frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'email_not_verified');

    $this->assertGuest();
});

it('blocks protected routes for unverified users when verification is required', function () {
    config(['app.require_email_verification' => true]);

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->getJson('/api/time-activities', frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'email_not_verified');
});

it('sends a password reset link', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson('/api/forgot-password', [
        'email' => $user->email,
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('message', 'If that email exists, a reset link has been sent.');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('returns the same forgot-password response for unknown emails', function () {
    Notification::fake();

    $this->postJson('/api/forgot-password', [
        'email' => 'unknown@example.com',
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('message', 'If that email exists, a reset link has been sent.');

    Notification::assertNothingSent();
});

it('resets a password with a valid token', function () {
    $user = User::factory()->create(['password' => 'old-password']);
    $previousRememberToken = $user->remember_token;
    $token = Password::createToken($user);

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('message', 'Password reset successfully.');

    $user->refresh();

    expect($user->remember_token)->not->toBe($previousRememberToken)
        ->and(strlen((string) $user->remember_token))->toBe(60);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'new-password',
    ], frontendHeaders())->assertOk();
});

it('invalidates existing sessions after password reset', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create(['password' => 'old-password']);

    DB::table('sessions')->insert([
        'id' => 'existing-session',
        'user_id' => $user->id,
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    $token = Password::createToken($user);

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ], frontendHeaders())->assertOk();

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

it('rejects password reset with an invalid token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This password reset token is invalid.');
});

it('validates forgot-password payload', function () {
    $this->postJson('/api/forgot-password', [
        'email' => 'not-an-email',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('validates reset-password payload', function () {
    $this->postJson('/api/reset-password', [], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token', 'email', 'password']);
});
