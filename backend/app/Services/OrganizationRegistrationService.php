<?php

/**
 * Creates organizations and founding administrator accounts during self-service signup.
 */

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationSlug;
use Illuminate\Support\Facades\DB;

/**
 * Provisions a new SaaS tenant and its first administrator user.
 */
class OrganizationRegistrationService
{
    /**
     * Creates an organization and administrator account from a registration payload.
     *
     * @param  array{name: string, email: string, password: string, organization_name?: string|null}  $validated  Validated registration payload.
     * @return User
     */
    public function registerAdministrator(array $validated): User
    {
        return DB::transaction(function () use ($validated): User {
            $organizationName = trim((string) ($validated['organization_name'] ?? ''));
            $organizationName = $organizationName !== ''
                ? $organizationName
                : $validated['name'].' organization';

            $organization = Organization::query()->create([
                'name' => $organizationName,
                'slug' => OrganizationSlug::uniqueFromName($organizationName),
            ]);

            $user = User::query()->create([
                'organization_id' => $organization->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $user->forceFill(['is_admin' => true])->save();

            return $user->fresh(['organization']);
        });
    }
}
