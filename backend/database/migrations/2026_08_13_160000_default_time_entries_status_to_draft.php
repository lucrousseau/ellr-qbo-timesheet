<?php

/**
 * Aligns time_entries.status column default with draft-first logging.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sets the status column default to draft on MySQL and PostgreSQL.
     *
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('time_entries')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE time_entries MODIFY status VARCHAR(32) NOT NULL DEFAULT 'draft'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE time_entries ALTER COLUMN status SET DEFAULT 'draft'");
        }
    }

    /**
     * Restores the legacy pending status column default.
     *
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasTable('time_entries')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE time_entries MODIFY status VARCHAR(32) NOT NULL DEFAULT 'pending'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE time_entries ALTER COLUMN status SET DEFAULT 'pending'");
        }
    }
};
