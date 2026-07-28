<?php

/**
 * Reusable validation rule arrays for new password fields.
 */

namespace App\Support;

/**
 * Centralizes password field rules for registration and reset endpoints.
 */
class PasswordRules
{
    /**
     * Validation rules for a new password with confirmation.
     *
     * @return array<int, mixed>
     */
    public static function newPassword(): array
    {
        return ['required', 'confirmed', PasswordPolicy::rule()];
    }
}
