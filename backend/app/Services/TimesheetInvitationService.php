<?php

/**
 * Sends timesheet onboarding emails with password-set deep links.
 */

namespace App\Services;

use App\Models\User;
use App\Notifications\InviteTimesheetUserNotification;
use Illuminate\Support\Facades\Password;

/**
 * Creates password-reset tokens and emails invite links to new timesheet users.
 */
class TimesheetInvitationService
{
    /**
     * Sends a timesheet password-set link when the broker throttle allows it.
     *
     * @param  User  $user  Newly provisioned timesheet user.
     * @return void
     */
    public function send(User $user): void
    {
        $broker = Password::broker();
        $token = $broker->createToken($user);
        $frontendUrl = app(PasswordResetLinkService::class)->resolveFrontendUrl('timesheet');

        $user->notify(
            (new InviteTimesheetUserNotification($token, $frontendUrl))
                ->locale($user->preferredLocale()),
        );
    }
}
