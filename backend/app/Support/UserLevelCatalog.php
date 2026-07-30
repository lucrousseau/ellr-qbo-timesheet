<?php

/**
 * Canonical permission tier definitions seeded into user_levels.
 */

namespace App\Support;

use App\Enums\UserLevelCode;

/**
 * Single source of truth for user_levels code and rank values.
 */
final class UserLevelCatalog
{
    /**
     * Returns ordered permission tier definitions for seeding and tests.
     *
     * @return list<array{code: string, rank: int}>
     */
    public static function definitions(): array
    {
        return [
            ['code' => UserLevelCode::Employee->value, 'rank' => 0],
            ['code' => UserLevelCode::Lead->value, 'rank' => 1],
            ['code' => UserLevelCode::Manager->value, 'rank' => 2],
        ];
    }
}
