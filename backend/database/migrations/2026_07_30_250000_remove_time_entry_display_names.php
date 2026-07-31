<?php

/**
 * Drops denormalized QuickBooks display names from local time entries.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removes cached picker labels in favor of ref-only storage.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'project_name', 'item_name']);
        });
    }

    /**
     * Restores denormalized picker label columns.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('customer_ref');
            $table->string('project_name')->nullable()->after('project_ref');
            $table->string('item_name')->nullable()->after('item_ref');
        });
    }
};
