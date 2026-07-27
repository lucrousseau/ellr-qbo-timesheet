<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

covers(VerifyEmailNotification::class);
covers(ResetPasswordNotification::class);

it('builds a verification email with a signed api link', function () {
    config(['app.frontend_auth_url' => 'http://localhost:5174']);

    $user = User::factory()->unverified()->create();
    $message = (new VerifyEmailNotification)->toMail($user);

    expect($message->subject)->toBe('Verify your email address')
        ->and($message->actionUrl)->toContain('/api/email/verify/');
});

it('builds a password reset email pointing to the timesheet app', function () {
    config(['app.frontend_auth_url' => 'http://localhost:5174']);

    $user = User::factory()->create();
    $message = (new ResetPasswordNotification('reset-token'))->toMail($user);

    expect($message->subject)->toBe('Reset your password')
        ->and($message->actionUrl)->toContain('http://localhost:5174/reset-password')
        ->and($message->actionUrl)->toContain('token=reset-token');
});

it('sends custom password reset notifications from the user model', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->sendPasswordResetNotification('token-value');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});
