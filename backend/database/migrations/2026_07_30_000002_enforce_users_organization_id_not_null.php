<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensures organization_id is NOT NULL on environments that ran an earlier nullable migration.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'organization_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable(false)->change();
        });
    }

    /**
     * Reverts the NOT NULL constraint (development rollback only).
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'organization_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->change();
        });
    }
};
