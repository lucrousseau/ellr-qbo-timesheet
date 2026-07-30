<?php

/**
 * Stable permission level codes stored in the user_levels lookup table.
 */

namespace App\Enums;

use App\Models\UserLevel;

/**
 * Known user permission tiers; display labels live in frontend i18n catalogs.
 *
 * Rank and ordering are stored on {@see UserLevel} rows only.
 */
enum UserLevelCode: string
{
    case Employee = 'employee';
    case Lead = 'lead';
    case Manager = 'manager';

    /**
     * Returns all supported level codes.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Returns the lowest permission level used for new accounts.
     *
     * @return self
     */
    public static function default(): self
    {
        return self::Employee;
    }
}
