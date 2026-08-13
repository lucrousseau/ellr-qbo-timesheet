<?php

/**
 * Local time entry write model pending supervisor approval before QBO sync.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the time_entries table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('customer_ref')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('project_ref')->nullable();
            $table->string('project_name')->nullable();
            $table->string('item_ref')->nullable();
            $table->string('item_name')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->text('description')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->string('status', 32)->default('draft');
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('qbo_id')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'start_time']);
            $table->index(['user_id', 'status', 'start_time']);
        });
    }

    /**
     * Drops the time_entries table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
