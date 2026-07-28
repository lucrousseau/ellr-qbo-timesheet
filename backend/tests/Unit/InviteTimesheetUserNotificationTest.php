<?php

use App\Models\User;
use App\Notifications\InviteTimesheetUserNotification;

covers(InviteTimesheetUserNotification::class);

it('builds a reset password url for the timesheet frontend', function () {
    config(['app.frontend_timesheet_url' => 'http://timesheet.test']);

    $user = User::factory()->make([
        'email' => 'jane@example.com',
    ]);

    $notification = new InviteTimesheetUserNotification('secret-token');

    expect($notification->toMail($user)->actionUrl)->toBe(
        'http://timesheet.test/reset-password?token=secret-token&email=jane%40example.com',
    );
});

it('uses a custom frontend base url when provided', function () {
    $user = User::factory()->make([
        'email' => 'jane@example.com',
    ]);

    $notification = new InviteTimesheetUserNotification('secret-token', 'http://custom.test/');

    expect($notification->toMail($user)->actionUrl)->toBe(
        'http://custom.test/reset-password?token=secret-token&email=jane%40example.com',
    );
});
