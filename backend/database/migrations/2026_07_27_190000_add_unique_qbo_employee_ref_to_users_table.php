<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensures each QuickBooks employee maps to at most one application user.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('qbo_employee_ref');
        });
    }

    /**
     * Drops the unique index on QuickBooks employee references.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['qbo_employee_ref']);
        });
    }
};
