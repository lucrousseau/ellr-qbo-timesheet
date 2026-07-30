<?php

/**
 * Idempotent development users for local and Docker environments.
 */

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds default admin and timesheet test accounts (local only).
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
        $this->seedAdmin($organization);
        $this->seedTimesheetUser($organization);
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
     * Creates or updates the development administrator account.
     *
     * @param  Organization  $organization  Development tenant organization.
     * @return void
     */
    private function seedAdmin(Organization $organization): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => (string) config('dev-seed.admin_email')],
            [
                'organization_id' => $organization->id,
                'name' => (string) config('dev-seed.admin_name'),
                'password' => (string) config('dev-seed.admin_password'),
                'email_verified_at' => now(),
            ],
        );

        $user->forceFill(['is_admin' => true])->save();
    }

    /**
     * Creates or updates a non-admin timesheet test account.
     *
     * @param  Organization  $organization  Development tenant organization.
     * @return void
     */
    private function seedTimesheetUser(Organization $organization): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => (string) config('dev-seed.user_email')],
            [
                'organization_id' => $organization->id,
                'name' => (string) config('dev-seed.user_name'),
                'password' => (string) config('dev-seed.user_password'),
                'email_verified_at' => now(),
            ],
        );

        $user->forceFill(['is_admin' => false])->save();
    }
}
