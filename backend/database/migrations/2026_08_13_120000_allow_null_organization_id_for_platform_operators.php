<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allows platform super administrators to exist without a tenant organization.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'organization_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->change();
        });
    }

    /**
     * Restores the NOT NULL organization membership constraint.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'organization_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable(false)->change();
        });
    }
};
