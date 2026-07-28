<?php

/**
 * Supported UI locales stored on user accounts.
 */

namespace App\Enums;

/**
 * Locales available for user-facing copy and Laravel validation messages.
 */
enum UserLocale: string
{
    case En = 'en';
    case Fr = 'fr';

    /**
     * Returns all supported locale codes.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
