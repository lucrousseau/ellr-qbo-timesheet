<?php

/**
 * Migration that creates the active_time_sessions table for timer persistence.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the active time session table for persistent timer state.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('active_time_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('customer_ref')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('project_ref')->nullable();
            $table->string('project_name')->nullable();
            $table->string('service_ref')->nullable();
            $table->string('service_name')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('accumulated_seconds')->default(0);
            $table->timestamp('running_since')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Drops the active time session table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('active_time_sessions');
    }
};
