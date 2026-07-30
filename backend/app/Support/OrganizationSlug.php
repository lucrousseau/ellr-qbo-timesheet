<?php

/**
 * Generates unique organization slugs for tenant provisioning.
 */

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Str;

/**
 * Builds collision-safe organization slug values from display names.
 */
class OrganizationSlug
{
    /**
     * Returns a unique slug derived from an organization display name.
     *
     * @param  string  $name  Organization display name.
     * @return string
     */
    public static function uniqueFromName(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'organization';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Organization::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
