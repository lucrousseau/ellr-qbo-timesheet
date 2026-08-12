<?php

/**
 * Model factory for generating TimeEntrySyncGroup records in tests.
 */

namespace Database\Factories;

use App\Models\TimeEntrySyncGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TimeEntrySyncGroup>
 */
class TimeEntrySyncGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'txn_date' => now()->toDateString(),
            'customer_ref' => null,
            'project_ref' => null,
            'item_ref' => '33',
            'is_billable' => false,
            'group_key' => 'factory-group-key',
            'member_count' => 1,
            'total_duration_seconds' => 3600,
            'qbo_id' => null,
            'qbo_description' => null,
            'synced_at' => null,
        ];
    }

    /**
     * Associates the group with a timesheet user and their organization.
     *
     * @param  User  $user  Employee whose entries were grouped.
     * @return static
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
        ]);
    }
}
