<?php

/**
 * Adds optional supervisor assignment for time entry approval routing.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the supervisor foreign key to users.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supervisor_id')
                ->nullable()
                ->after('user_level_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Removes the supervisor foreign key from users.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
        });
    }
};
