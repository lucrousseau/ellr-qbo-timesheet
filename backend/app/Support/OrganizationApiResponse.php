<?php

/**
 * Serializes tenant organizations for JSON API responses.
 */

namespace App\Support;

use App\Models\Organization;

/**
 * Builds organization payloads nested under authenticated user responses.
 */
class OrganizationApiResponse
{
    /**
     * Returns API-visible organization attributes for JSON encoding.
     *
     * @param  Organization  $organization  Tenant organization instance.
     * @return array{id: int, name: string, slug: string, qbo_connected: bool, company_timezone: string|null}
     */
    public static function resource(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'qbo_connected' => $organization->hasQuickBooksRealm(),
            'company_timezone' => $organization->company_timezone,
        ];
    }
}
