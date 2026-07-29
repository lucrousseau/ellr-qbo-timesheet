<?php

/**
 * Adds explicit all-customers access flag for timesheet users.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the QuickBooks all-customers access column.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('qbo_all_customers_access')->default(false)->after('qbo_employee_name');
        });
    }

    /**
     * Removes the QuickBooks all-customers access column.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('qbo_all_customers_access');
        });
    }
};
