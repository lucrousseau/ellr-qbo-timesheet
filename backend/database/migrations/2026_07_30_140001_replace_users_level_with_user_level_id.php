<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces users.level string codes with a foreign key to user_levels.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('user_level_id')
                ->nullable()
                ->after('locale')
                ->constrained('user_levels');
        });

        $levelIds = DB::table('user_levels')->pluck('id', 'code');
        $defaultLevelId = $levelIds['employee'] ?? null;

        if ($defaultLevelId === null) {
            throw new RuntimeException('The employee user level must be seeded before migrating users.');
        }

        if (Schema::hasColumn('users', 'level')) {
            foreach ($levelIds as $code => $id) {
                DB::table('users')->where('level', $code)->update(['user_level_id' => $id]);
            }

            DB::table('users')->whereNull('user_level_id')->update(['user_level_id' => $defaultLevelId]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('level');
            });
        } else {
            DB::table('users')->whereNull('user_level_id')->update(['user_level_id' => $defaultLevelId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('user_level_id')->nullable(false)->change();
        });
    }

    /**
     * Restores users.level string codes from user_levels.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('level', 32)->default('employee')->after('locale');
        });

        $levels = DB::table('user_levels')->pluck('code', 'id');

        foreach ($levels as $id => $code) {
            DB::table('users')->where('user_level_id', $id)->update(['level' => $code]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_level_id');
        });
    }
};
