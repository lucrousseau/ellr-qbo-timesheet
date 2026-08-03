<?php

/**
 * Adds structured ticket metadata to timer sessions and local time entries.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('active_time_sessions', function (Blueprint $table): void {
            $table->string('ticket_key', 64)->nullable()->after('description');
            $table->string('ticket_source', 32)->nullable()->after('ticket_key');
            $table->string('ticket_url', 2048)->nullable()->after('ticket_source');
            $table->string('ticket_title', 512)->nullable()->after('ticket_url');
        });

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->string('ticket_key', 64)->nullable()->after('description');
            $table->string('ticket_source', 32)->nullable()->after('ticket_key');
            $table->string('ticket_url', 2048)->nullable()->after('ticket_source');
            $table->string('ticket_title', 512)->nullable()->after('ticket_url');
            $table->index('ticket_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('active_time_sessions', function (Blueprint $table): void {
            $table->dropColumn(['ticket_key', 'ticket_source', 'ticket_url', 'ticket_title']);
        });

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropIndex(['ticket_key']);
            $table->dropColumn(['ticket_key', 'ticket_source', 'ticket_url', 'ticket_title']);
        });
    }
};
