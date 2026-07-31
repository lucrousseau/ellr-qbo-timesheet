<?php

/**
 * Adds indexes to support reconcile purge and high-volume snapshot queries.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds snapshot reconcile and approval query indexes.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('time_activity_snapshots', function (Blueprint $table) {
            $table->index(['realm_id', 'txn_date'], 'snapshots_realm_txn_date_idx');
        });
    }

    /**
     * Drops the scalability indexes.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('time_activity_snapshots', function (Blueprint $table) {
            $table->dropIndex('snapshots_realm_txn_date_idx');
        });
    }
};
