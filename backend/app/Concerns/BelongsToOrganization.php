<?php

/**
 * Query scopes that constrain Eloquent models to a tenant organization.
 */

namespace App\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared organization scoping helpers for tenant-owned models.
 */
trait BelongsToOrganization
{
    /**
     * Limits the query to rows owned by the given organization.
     *
     * @param  Builder<static>  $query  Eloquent query builder.
     * @param  Organization|int  $organization  Tenant organization or id.
     * @return Builder<static>
     */
    public function scopeForOrganization(Builder $query, Organization|int $organization): Builder
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $query->where($this->qualifyColumn('organization_id'), $organizationId);
    }
}
