<?php

/**
 * Stores the reviewer's QuickBooks grouping choice on each approved entry.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the opt-in grouping flag used when approving time entries.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->boolean('group_for_qbo')->default(false)->after('rejection_reason');
        });
    }

    /**
     * Removes the opt-in grouping flag.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn('group_for_qbo');
        });
    }
};
