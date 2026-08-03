<?php

/**
 * Local expense write model pending supervisor approval before QBO Purchase sync.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the expenses table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('txn_date');
            $table->string('payment_type', 32)->default('Cash');
            $table->string('payment_account_ref');
            $table->string('expense_account_ref');
            $table->string('vendor_ref')->nullable();
            $table->string('customer_ref')->nullable();
            $table->string('project_ref')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->string('status', 32)->default('pending');
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('qbo_id')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'txn_date']);
            $table->index(['user_id', 'status', 'txn_date']);
        });
    }

    /**
     * Drops the expenses table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
