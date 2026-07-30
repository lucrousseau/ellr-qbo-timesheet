<?php

/**
 * Lookup row for tenant user permission tiers referenced by users.user_level_id.
 */

namespace App\Models;

use App\Enums\UserLevelCode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'rank'])]
/**
 * Permission tier with a stable code and ordered rank.
 */
class UserLevel extends Model
{
    /**
     * Users assigned to this permission tier.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Indicates whether this tier grants at least the given minimum rank.
     *
     * @param  UserLevel  $minimum  Minimum tier to compare against.
     * @return bool
     */
    public function isAtLeast(self $minimum): bool
    {
        return $this->rank >= $minimum->rank;
    }

    /**
     * Returns the backed enum case when the stored code is known.
     *
     * @return UserLevelCode|null
     */
    public function tryCode(): ?UserLevelCode
    {
        return UserLevelCode::tryFrom($this->code);
    }
}
