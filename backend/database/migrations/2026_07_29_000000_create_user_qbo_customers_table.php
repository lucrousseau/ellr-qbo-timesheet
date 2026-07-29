<?php

/**
 * Stores administrator-assigned QuickBooks customers per timesheet user.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the user-to-customer assignment table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('user_qbo_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('qbo_customer_ref');
            $table->string('qbo_customer_name');
            $table->unique(['user_id', 'qbo_customer_ref']);
        });
    }

    /**
     * Drops the user-to-customer assignment table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('user_qbo_customers');
    }
};
