<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

covers(VerifyEmailNotification::class);
covers(ResetPasswordNotification::class);

it('builds a verification email with a signed api link', function () {
    config([
        'app.frontend_auth_url' => 'http://localhost:5174',
        'auth.verification.expire' => 45,
    ]);

    $user = User::factory()->unverified()->create();
    $notification = new VerifyEmailNotification;
    $message = $notification->toMail($user);
    parse_str((string) parse_url($message->actionUrl, PHP_URL_QUERY), $query);

    expect($message->subject)->toBe('Verify your email address')
        ->and($message->actionUrl)->toContain('/api/email/verify/')
        ->and((int) $query['expires'])->toBeGreaterThan(now()->addMinutes(44)->getTimestamp())
        ->and((int) $query['expires'])->toBeLessThan(now()->addMinutes(46)->getTimestamp());
});

it('uses the default verification expiry when config is absent', function () {
    config(['app.frontend_auth_url' => 'http://localhost:5174']);

    $verification = config('auth.verification');
    unset($verification['expire']);
    config(['auth.verification' => $verification]);

    $user = User::factory()->unverified()->create();
    $message = (new VerifyEmailNotification)->toMail($user);
    parse_str((string) parse_url($message->actionUrl, PHP_URL_QUERY), $query);

    expect(config('auth.verification.expire'))->toBeNull()
        ->and((int) $query['expires'])->toBeGreaterThan(now()->addMinutes(59)->getTimestamp())
        ->and((int) $query['expires'])->toBeLessThan(now()->addMinutes(61)->getTimestamp());
});

it('builds a password reset email pointing to the timesheet app', function () {
    config(['app.frontend_auth_url' => 'http://localhost:5174/']);

    $user = User::factory()->create();
    $message = (new ResetPasswordNotification('reset-token'))->toMail($user);

    expect($message->subject)->toBe('Reset your password')
        ->and($message->actionUrl)->toBe('http://localhost:5174/reset-password?token=reset-token&email='.urlencode($user->email));
});

it('sends custom password reset notifications from the user model', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->sendPasswordResetNotification('token-value');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});
