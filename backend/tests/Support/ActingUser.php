<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class ActingUser
{
    /**
     * Return the user authenticated in the current test request.
     */
    public static function current(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
