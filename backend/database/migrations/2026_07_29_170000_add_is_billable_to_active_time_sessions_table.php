<?php

/**
 * Migration that adds billable flag storage to active time sessions.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the billable flag column to active time sessions.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('active_time_sessions', function (Blueprint $table) {
            $table->boolean('is_billable')->default(false)->after('description');
        });
    }

    /**
     * Removes the billable flag column from active time sessions.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('active_time_sessions', function (Blueprint $table) {
            $table->dropColumn('is_billable');
        });
    }
};
