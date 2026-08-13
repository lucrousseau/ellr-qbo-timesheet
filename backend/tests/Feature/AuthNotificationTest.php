<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;

covers(VerifyEmailNotification::class);
covers(ResetPasswordNotification::class);

it('builds a verification email with a signed api link', function () {
    config([
        'app.url' => 'http://localhost:8000',
        'app.frontend_auth_url' => 'http://localhost:5174',
        'auth.verification.expire' => 45,
    ]);

    $user = User::factory()->unverified()->create();
    $notification = new VerifyEmailNotification;
    $message = $notification->toMail($user);
    parse_str((string) parse_url($message->actionUrl, PHP_URL_QUERY), $query);

    expect($message->subject)->toBe('Verify your email address')
        ->and($message->actionUrl)->toStartWith('http://localhost:8000/api/email/verify/')
        ->and((int) $query['expires'])->toBeGreaterThan(now()->addMinutes(44)->getTimestamp())
        ->and((int) $query['expires'])->toBeLessThan(now()->addMinutes(46)->getTimestamp());
});

it('ignores docker proxy request hosts when building verification links', function () {
    config(['app.url' => 'http://localhost:8000']);

    $request = Request::create('http://api:8000/api/register', 'POST');
    app()->instance('request', $request);
    url()->forceRootUrl(null);

    $user = User::factory()->unverified()->create();
    $message = (new VerifyEmailNotification)->toMail($user);

    expect($message->actionUrl)
        ->toStartWith('http://localhost:8000/api/email/verify/')
        ->not->toContain('http://api:8000');
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

it('builds a password reset email pointing to the admin app', function () {
    $user = User::factory()->create();
    $message = (new ResetPasswordNotification('reset-token', 'http://localhost:5173'))->toMail($user);

    expect($message->actionUrl)->toBe('http://localhost:5173/reset-password?token=reset-token&email='.urlencode($user->email));
});

it('sends custom password reset notifications from the user model', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->sendPasswordResetNotification('token-value');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('builds a french verification email when the app locale is french', function () {
    App::setLocale('fr');

    $user = User::factory()->unverified()->create();
    $message = (new VerifyEmailNotification)->toMail($user);

    expect($message->subject)->toBe(__('mail.verify.subject', [], 'fr'))
        ->and($message->introLines[0])->toBe(__('mail.verify.line', [], 'fr'));
});

it('builds a french password reset email when the app locale is french', function () {
    App::setLocale('fr');

    $user = User::factory()->create();
    $message = (new ResetPasswordNotification('reset-token'))->toMail($user);

    expect($message->subject)->toBe(__('mail.reset.subject', [], 'fr'))
        ->and($message->introLines[0])->toBe(__('mail.reset.line', [], 'fr'));
});
