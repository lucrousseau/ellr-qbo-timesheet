<?php

/**
 * Drops cached QuickBooks display names from mapping and timer tables.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removes denormalized QBO labels in favor of ref-only storage.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('qbo_employee_name');
        });

        Schema::table('user_qbo_customers', function (Blueprint $table) {
            $table->dropColumn('qbo_customer_name');
        });

        Schema::table('active_time_sessions', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'project_name', 'service_name']);
        });
    }

    /**
     * Restores denormalized QBO label columns.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('qbo_employee_name')->nullable()->after('qbo_employee_ref');
        });

        Schema::table('user_qbo_customers', function (Blueprint $table) {
            $table->string('qbo_customer_name')->nullable()->after('qbo_customer_ref');
        });

        Schema::table('active_time_sessions', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('customer_ref');
            $table->string('project_name')->nullable()->after('project_ref');
            $table->string('service_name')->nullable()->after('service_ref');
        });
    }
};
