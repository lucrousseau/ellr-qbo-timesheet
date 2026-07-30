<?php

/**
 * Creates organizations and founding administrator accounts during self-service signup.
 */

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                'slug' => $this->uniqueSlug($organizationName),
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

    /**
     * Builds a unique organization slug from a display name.
     *
     * @param  string  $name  Organization display name.
     * @return string
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'organization';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Organization::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
