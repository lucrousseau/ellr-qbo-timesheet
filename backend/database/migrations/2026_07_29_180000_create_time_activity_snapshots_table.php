<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_activity_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('realm_id');
            $table->string('qbo_id');
            $table->string('qbo_employee_ref');
            $table->string('customer_ref')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('project_ref')->nullable();
            $table->string('project_name')->nullable();
            $table->string('item_ref')->nullable();
            $table->string('item_name')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->date('txn_date')->nullable();
            $table->text('description')->nullable();
            $table->string('billable_status')->nullable();
            $table->string('qbo_sync_token')->nullable();
            $table->timestamp('qbo_last_updated_at')->nullable();
            $table->timestamp('last_synced_at');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['realm_id', 'qbo_id']);
            $table->index(['realm_id', 'qbo_employee_ref', 'start_time']);
            $table->index(['realm_id', 'deleted_at']);
        });

        Schema::create('qbo_realm_sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('realm_id')->unique();
            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qbo_realm_sync_states');
        Schema::dropIfExists('time_activity_snapshots');
    }
};
