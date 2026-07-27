<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('qbo_employee_ref', 255)->nullable()->after('email');
            $table->string('qbo_employee_name', 255)->nullable()->after('qbo_employee_ref');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['qbo_employee_ref', 'qbo_employee_name']);
        });
    }
};
