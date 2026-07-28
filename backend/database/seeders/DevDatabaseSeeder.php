<?php

/**
 * Idempotent development users for local and Docker environments.
 */

namespace Database\Seeders;

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

        $this->seedAdmin();
        $this->seedTimesheetUser();
    }

    /**
     * Creates or updates the development administrator account.
     *
     * @return void
     */
    private function seedAdmin(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => (string) config('dev-seed.admin_email')],
            [
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
     * @return void
     */
    private function seedTimesheetUser(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => (string) config('dev-seed.user_email')],
            [
                'name' => (string) config('dev-seed.user_name'),
                'password' => (string) config('dev-seed.user_password'),
                'email_verified_at' => now(),
            ],
        );

        $user->forceFill(['is_admin' => false])->save();
    }
}
