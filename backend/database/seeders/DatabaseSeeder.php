<?php

/**
 * Database seeder entry point for local and test environments.
 */

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the default development database records when APP_ENV is local.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        if (! app()->environment('local') || ! config('dev-seed.enabled')) {
            return;
        }

        $this->call(DevDatabaseSeeder::class);
    }
}
