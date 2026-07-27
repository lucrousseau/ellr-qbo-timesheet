<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\AuthSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

covers(AuthSessionService::class);

uses(RefreshDatabase::class);

it('rejects unverified logins when verification is required', function () {
    config(['app.require_email_verification' => true]);

    $user = User::factory()->unverified()->create();
    $request = Request::create('/api/login', 'POST');
    $request->setLaravelSession(app('session.store'));

    Auth::login($user);

    $response = app(AuthSessionService::class)->rejectUnverifiedLogin($user, $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(403)
        ->and($response->getData(true)['error'])->toBe('email_not_verified');
});

it('allows verified logins when verification is required', function () {
    config(['app.require_email_verification' => true]);

    $user = User::factory()->create();
    $request = Request::create('/api/login', 'POST');

    expect(app(AuthSessionService::class)->rejectUnverifiedLogin($user, $request))->toBeNull();
});

it('sends verification email when required for new users', function () {
    Notification::fake();

    config(['app.require_email_verification' => true]);

    $user = User::factory()->unverified()->create();

    app(AuthSessionService::class)->sendVerificationIfRequired($user);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('invalidates database sessions and api tokens for a user', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create();
    $user->createToken('test-token');

    DB::table('sessions')->insert([
        'id' => 'session-1',
        'user_id' => $user->id,
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    app(AuthSessionService::class)->invalidateUserSessions($user);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->tokens()->count())->toBe(0);
});
