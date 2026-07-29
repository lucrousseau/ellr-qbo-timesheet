<?php

/**
 * Preserves prior implicit client access for provisioned timesheet users.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grants all-customers access to existing provisioned timesheet accounts.
     *
     * @return void
     */
    public function up(): void
    {
        DB::table('users')
            ->where('is_admin', false)
            ->whereNotNull('qbo_employee_ref')
            ->where('qbo_employee_ref', '!=', '')
            ->update(['qbo_all_customers_access' => true]);
    }

    /**
     * Reverts the migration.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('users')
            ->where('is_admin', false)
            ->whereNotNull('qbo_employee_ref')
            ->where('qbo_employee_ref', '!=', '')
            ->update(['qbo_all_customers_access' => false]);
    }
};
