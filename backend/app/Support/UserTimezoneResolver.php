<?php

/**
 * Resolves effective display timezones for authenticated users.
 */

namespace App\Support;

use App\Models\User;
use App\Services\OrganizationTimezoneService;

/**
 * Chooses the timezone used to render timestamps in client applications.
 */
final class UserTimezoneResolver
{
    /**
     * Returns the timezone used to format timestamps for the signed-in user.
     *
     * Preference order: user override, organization company timezone, deployment fallback.
     *
     * @param  User  $user  Authenticated user.
     * @return string
     */
    public static function effectiveDisplayTimezone(User $user): string
    {
        $userTimezone = TimezoneIdentifier::normalize($user->timezone);

        if ($userTimezone !== null) { // @pest-mutate-ignore user timezone preference shortcut
            return $userTimezone;
        }

        return app(OrganizationTimezoneService::class)->forOrganization($user->organization); // @pest-mutate-ignore organization timezone fallback
    }
}
