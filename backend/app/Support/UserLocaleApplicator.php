<?php

/**
 * Applies a user's stored locale to the Laravel translator for the current request.
 */

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\App;

/**
 * Sets App locale from a user record or email lookup (guest auth flows).
 */
class UserLocaleApplicator
{
    /**
     * Applies the preferred locale when a user is known.
     *
     * @param  User|null  $user  Authenticated or looked-up user.
     * @return void
     */
    public static function applyForUser(?User $user): void
    {
        if ($user !== null) {
            App::setLocale($user->preferredLocale());
        }
    }

    /**
     * Resolves a user by email and applies their preferred locale when found.
     *
     * @param  string|null  $email  Email address from a guest auth payload.
     * @return void
     */
    public static function applyForEmail(?string $email): void
    {
        if ($email === null || $email === '') {
            return;
        }

        $user = User::query()->where('email', $email)->first();
        self::applyForUser($user);
    }
}
