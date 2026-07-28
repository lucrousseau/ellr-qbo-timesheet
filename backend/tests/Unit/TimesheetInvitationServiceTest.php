<?php

use App\Models\User;
use App\Notifications\InviteTimesheetUserNotification;
use App\Services\TimesheetInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

covers(TimesheetInvitationService::class);

uses(RefreshDatabase::class);

it('sends a timesheet invitation notification with a password reset token', function () {
    Notification::fake();

    $user = User::factory()->create([
        'locale' => 'fr',
    ]);

    app(TimesheetInvitationService::class)->send($user);

    Notification::assertSentTo(
        $user,
        InviteTimesheetUserNotification::class,
        function (InviteTimesheetUserNotification $notification) use ($user): bool {
            return $notification->locale === 'fr'
                && str_contains($notification->toMail($user)->actionUrl, '/reset-password?');
        },
    );
});
