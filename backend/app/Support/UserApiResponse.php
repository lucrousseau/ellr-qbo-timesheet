<?php

/**
 * Serializes authenticated users for JSON API responses.
 */

namespace App\Support;

use App\Models\User;

/**
 * Builds user payloads exposed to admin and timesheet clients.
 *
 * Exposes {@see User::$is_admin} so the admin app can show administrator-only tabs.
 */
class UserApiResponse
{
    /**
     * Returns the user model with API-visible attributes for JSON encoding.
     *
     * @param  User  $user  Authenticated user instance.
     * @return User
     */
    public static function resource(User $user): User
    {
        $user->makeVisible(['is_admin']);
        $user->setAttribute('is_admin', $user->isAdmin());

        return $user;
    }
}
