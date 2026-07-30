<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Superseded by user_levels and users.user_level_id migrations.
     */
    public function up(): void {}

    /**
     * No-op rollback for the superseded users.level column migration.
     */
    public function down(): void {}
};
