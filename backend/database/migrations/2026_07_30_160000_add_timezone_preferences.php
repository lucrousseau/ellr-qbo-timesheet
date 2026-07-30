<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('company_timezone', 64)->nullable()->after('realm_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('company_timezone');
        });
    }
};
