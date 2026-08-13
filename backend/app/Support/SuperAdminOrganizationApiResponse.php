<?php

/**
 * Serializes tenant organizations for super-administrator management APIs.
 */

namespace App\Support;

use App\Models\Organization;

/**
 * Builds organization payloads for platform operator dashboards.
 */
class SuperAdminOrganizationApiResponse
{
    /**
     * Returns API-visible organization attributes for super-administrator clients.
     *
     * @param  Organization  $organization  Tenant organization instance.
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     qbo_connected: bool,
     *     users_count: int,
     *     created_at: string|null,
     *     administrator: array{first_name: string, last_name: string, email: string}|null
     * }
     */
    public static function resource(Organization $organization): array
    {
        $organization->loadMissing('administrator');
        $administrator = $organization->administrator;

        return [
            ...OrganizationApiResponse::resource($organization),
            'users_count' => (int) ($organization->users_count ?? $organization->users()->count()),
            'created_at' => $organization->created_at?->toIso8601String(),
            'administrator' => $administrator === null ? null : [
                'first_name' => (string) $administrator->first_name,
                'last_name' => (string) $administrator->last_name,
                'email' => (string) $administrator->email,
            ],
        ];
    }
}
