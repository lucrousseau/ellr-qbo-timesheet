<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Links users to organizations and scopes QuickBooks employee mappings per tenant.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });

        if (User::query()->exists()) {
            $organization = Organization::query()->create([
                'name' => 'Default organization',
                'slug' => 'default-'.Str::lower(Str::random(8)),
            ]);

            User::query()
                ->whereNull('organization_id')
                ->update(['organization_id' => $organization->id]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['qbo_employee_ref']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['organization_id', 'qbo_employee_ref']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable(false)->change();
        });
    }

    /**
     * Removes organization scoping from users.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'qbo_employee_ref']);
            $table->dropConstrainedForeignId('organization_id');
            $table->unique('qbo_employee_ref');
        });
    }
};
