<?php

/**
 * Tenant organization that owns application users and one QuickBooks company realm.
 */

namespace App\Models;

use App\Services\OrganizationRealmDataPurgeService;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'realm_id'])]
/**
 * SaaS tenant with optional QuickBooks Online company binding.
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * Purges QuickBooks realm data before the tenant row is removed.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::deleting(function (Organization $organization): void {
            if ($organization->hasQuickBooksRealm()) {
                app(OrganizationRealmDataPurgeService::class)->purgeRealm($organization->realm_id);
            }
        });
    }

    /**
     * Users that belong to this organization.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Indicates whether a QuickBooks company realm is linked to this organization.
     *
     * @return bool
     */
    public function hasQuickBooksRealm(): bool
    {
        return is_string($this->realm_id) && $this->realm_id !== '';
    }
}
