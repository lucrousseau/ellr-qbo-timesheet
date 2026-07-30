<?php

/**
 * Resolves user_levels.id values from stable permission codes.
 */

namespace App\Services;

use App\Enums\UserLevelCode;
use App\Models\UserLevel;
use RuntimeException;

/**
 * Caches user_levels primary keys for provisioning and model defaults.
 */
class UserLevelResolverService
{
    /**
     * Cached primary keys keyed by stable level code.
     *
     * @var array<string, int>
     */
    private array $idsByCode = [];

    /**
     * Returns the database id for a seeded permission level code.
     *
     * @param  UserLevelCode  $code  Stable permission code.
     * @return int
     */
    public function idFor(UserLevelCode $code): int
    {
        if (isset($this->idsByCode[$code->value])) {
            return $this->idsByCode[$code->value];
        }

        $id = UserLevel::query()->where('code', $code->value)->value('id');

        if ($id === null) {
            throw new RuntimeException("User level [{$code->value}] is not seeded.");
        }

        $this->idsByCode[$code->value] = (int) $id;

        return (int) $id;
    }

    /**
     * Returns the database id for the default lowest permission tier.
     *
     * @return int
     */
    public function defaultId(): int
    {
        return $this->idFor(UserLevelCode::default());
    }
}
