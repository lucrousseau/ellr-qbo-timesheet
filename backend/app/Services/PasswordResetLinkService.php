<?php

/**
 * Sends password reset links with per-client frontend deep links.
 */

namespace App\Services;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Password;

/**
 * Creates reset tokens and notifies users without revealing account existence.
 */
class PasswordResetLinkService
{
    /**
     * Sends a reset link when the email matches an account and broker throttle allows it.
     *
     * @param  string  $email  Account email address.
     * @param  string|null  $client  Optional frontend client (`admin` or `timesheet`).
     * @return void
     */
    public function send(string $email, ?string $client = null): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $broker = Password::broker();

        if ($broker->getRepository()->recentlyCreatedToken($user)) {
            return;
        }

        $token = $broker->createToken($user);
        $frontendUrl = $this->resolveFrontendUrl($client);

        $user->notify(new ResetPasswordNotification($token, $frontendUrl));
    }

    /**
     * Resolves the frontend base URL for a password reset deep link.
     *
     * @param  string|null  $client  Optional frontend client (`admin` or `timesheet`).
     * @return string
     */
    public function resolveFrontendUrl(?string $client): string
    {
        if ($client === 'admin') {
            return rtrim((string) config('app.frontend_admin_url'), '/');
        }

        if ($client === 'timesheet') {
            return rtrim((string) config('app.frontend_timesheet_url'), '/');
        }

        return rtrim((string) config('app.frontend_auth_url'), '/');
    }
}
