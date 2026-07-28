<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\PasswordResetLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

covers(PasswordResetLinkService::class);

uses(RefreshDatabase::class);

it('sends a reset notification for known emails', function () {
    Notification::fake();

    $user = User::factory()->create();

    app(PasswordResetLinkService::class)->send($user->email);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('does not send a reset notification for unknown emails', function () {
    Notification::fake();

    app(PasswordResetLinkService::class)->send('unknown@example.com');

    Notification::assertNothingSent();
});

it('targets the admin frontend when client is admin', function () {
    Notification::fake();

    config(['app.frontend_admin_url' => 'http://localhost:5173']);

    $user = User::factory()->create();

    app(PasswordResetLinkService::class)->send($user->email, 'admin');

    Notification::assertSentTo(
        $user,
        function (ResetPasswordNotification $notification): bool {
            return $notification->frontendUrl === 'http://localhost:5173';
        },
    );
});

it('throttles repeat reset links for the same email', function () {
    Notification::fake();

    $user = User::factory()->create();

    $service = app(PasswordResetLinkService::class);

    $service->send($user->email);
    $service->send($user->email);

    Notification::assertSentTimes(ResetPasswordNotification::class, 1);
});

it('sends reset notifications in the user locale', function () {
    Notification::fake();

    $user = User::factory()->create(['locale' => 'fr']);

    app(PasswordResetLinkService::class)->send($user->email);

    Notification::assertSentTo(
        $user,
        fn (ResetPasswordNotification $notification): bool => $notification->locale === 'fr',
    );
});

it('targets the timesheet frontend when client is timesheet', function () {
    Notification::fake();

    config(['app.frontend_timesheet_url' => 'http://localhost:5174/']);

    $user = User::factory()->create();

    app(PasswordResetLinkService::class)->send($user->email, 'timesheet');

    Notification::assertSentTo(
        $user,
        fn (ResetPasswordNotification $notification): bool => $notification->frontendUrl === 'http://localhost:5174',
    );
});

it('targets the shared auth frontend when client is unknown', function () {
    Notification::fake();

    config(['app.frontend_auth_url' => 'http://localhost:5180/']);

    $user = User::factory()->create();

    app(PasswordResetLinkService::class)->send($user->email, 'mobile');

    Notification::assertSentTo(
        $user,
        fn (ResetPasswordNotification $notification): bool => $notification->frontendUrl === 'http://localhost:5180',
    );
});

it('resolves frontend urls through the public helper', function () {
    config([
        'app.frontend_admin_url' => 'http://localhost:5173/',
        'app.frontend_timesheet_url' => 'http://localhost:5174/',
        'app.frontend_auth_url' => 'http://localhost:5180/',
    ]);

    $service = app(PasswordResetLinkService::class);

    expect($service->resolveFrontendUrl('admin'))->toBe('http://localhost:5173')
        ->and($service->resolveFrontendUrl('timesheet'))->toBe('http://localhost:5174')
        ->and($service->resolveFrontendUrl(null))->toBe('http://localhost:5180');
});
