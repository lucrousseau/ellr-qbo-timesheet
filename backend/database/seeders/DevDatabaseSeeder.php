<?php

/**
 * Idempotent development users for local and Docker environments.
 */

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds a sample tenant organization and platform operator (local only).
 */
class DevDatabaseSeeder extends Seeder
{
    /**
     * Create or refresh development users from DEV_SEED_* environment variables.
     *
     * @return void
     */
    public function run(): void
    {
        if (! app()->environment('local') || ! config('dev-seed.enabled')) {
            return;
        }

        $organization = $this->seedOrganization();
        $this->seedTenantAdmin($organization);
        $this->seedPlatformOperator($organization);
    }

    /**
     * Creates or updates the default development organization.
     *
     * @return Organization
     */
    private function seedOrganization(): Organization
    {
        return Organization::query()->updateOrCreate(
            ['slug' => (string) config('dev-seed.organization_slug', 'ellr-dev')],
            [
                'name' => (string) config('dev-seed.organization_name', 'Ellr Development'),
            ],
        );
    }

    /**
     * Creates or updates the tenant administrator for the sample client organization.
     *
     * @param  Organization  $organization  Development tenant organization.
     * @return void
     */
    private function seedTenantAdmin(Organization $organization): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => (string) config('dev-seed.tenant_admin_email')],
            [
                'organization_id' => $organization->id,
                'name' => (string) config('dev-seed.tenant_admin_name'),
                'password' => (string) config('dev-seed.tenant_admin_password'),
                'email_verified_at' => now(),
            ],
        );

        $user->forceFill([
            'is_admin' => true,
            'is_super_admin' => false,
        ])->save();
    }

    /**
     * Creates or updates the Ellr platform operator account (client org management).
     *
     * @param  Organization  $organization  Development tenant organization.
     * @return void
     */
    private function seedPlatformOperator(Organization $organization): void
    {
        if (! config('dev-seed.platform_enabled', true)) {
            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => (string) config('dev-seed.platform_email')],
            [
                'organization_id' => $organization->id,
                'name' => (string) config('dev-seed.platform_name'),
                'password' => (string) config('dev-seed.platform_password'),
                'email_verified_at' => now(),
            ],
        );

        $user->forceFill([
            'is_admin' => false,
            'is_super_admin' => true,
        ])->save();
    }
}
