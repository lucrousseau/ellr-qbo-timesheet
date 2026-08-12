<?php

/**
 * Sync groups that map multiple local time entries to one QuickBooks TimeActivity.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates sync groups and links time entries to them.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('time_entry_sync_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('txn_date');
            $table->string('customer_ref')->nullable();
            $table->string('project_ref')->nullable();
            $table->string('item_ref')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->string('group_key', 191);
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('total_duration_seconds')->default(0);
            $table->string('qbo_id')->nullable();
            $table->text('qbo_description')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'group_key']);
            $table->index(['user_id', 'txn_date']);
            $table->index(['qbo_id']);
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->foreignId('sync_group_id')
                ->nullable()
                ->after('qbo_id')
                ->constrained('time_entry_sync_groups')
                ->nullOnDelete();

            $table->index(['sync_group_id']);
        });
    }

    /**
     * Drops sync group linkage and the sync groups table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sync_group_id');
        });

        Schema::dropIfExists('time_entry_sync_groups');
    }
};
