<?php

/**
 * Adds indexes for frequently filtered time entry and user approval queries.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds performance indexes for merged list and approval queries.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->index(['user_id', 'qbo_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['organization_id', 'is_admin', 'qbo_employee_ref'], 'users_org_admin_employee_idx');
        });
    }

    /**
     * Drops the performance indexes.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'qbo_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_org_admin_employee_idx');
        });
    }
};
