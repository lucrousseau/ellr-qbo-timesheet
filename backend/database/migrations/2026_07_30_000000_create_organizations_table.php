<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the organizations table for multi-tenant SaaS isolation.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('realm_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Drops the organizations table.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
