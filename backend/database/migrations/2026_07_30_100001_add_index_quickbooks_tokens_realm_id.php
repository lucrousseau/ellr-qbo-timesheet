<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes QuickBooks realm lookups used by webhooks and reconcile jobs.
     */
    public function up(): void
    {
        Schema::table('quickbooks_tokens', function (Blueprint $table) {
            $table->index('realm_id');
        });
    }

    /**
     * Drops the QuickBooks realm lookup index.
     */
    public function down(): void
    {
        Schema::table('quickbooks_tokens', function (Blueprint $table) {
            $table->dropIndex(['realm_id']);
        });
    }
};
