<?php

use Database\Seeders\UserLevelSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the user_levels lookup table and seeds canonical permission tiers.
     */
    public function up(): void
    {
        Schema::create('user_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->unsignedTinyInteger('rank');
            $table->timestamps();
        });

        (new UserLevelSeeder)->run();
    }

    /**
     * Drops the user_levels lookup table.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_levels');
    }
};
