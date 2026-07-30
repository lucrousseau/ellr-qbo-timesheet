<?php

/**
 * Seeds global user permission tiers referenced by users.user_level_id.
 */

namespace Database\Seeders;

use App\Support\UserLevelCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Inserts or updates the canonical user_levels lookup rows.
 */
class UserLevelSeeder extends Seeder
{
    /**
     * Seeds permission tiers ordered from lowest to highest privilege.
     *
     * @return void
     */
    public function run(): void
    {
        $now = now();

        foreach (UserLevelCatalog::definitions() as $definition) {
            DB::table('user_levels')->updateOrInsert(
                ['code' => $definition['code']],
                [
                    'rank' => $definition['rank'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }
}
