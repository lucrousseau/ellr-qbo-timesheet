<?php

/**
 * Model factory for generating TimeEntry records in tests.
 */

namespace Database\Factories;

use App\Enums\TimeEntryStatus;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subHours(2);
        $end = now()->subHour();

        return [
            'start_time' => $start,
            'end_time' => $end,
            'description' => fake()->optional()->sentence(),
            'is_billable' => false,
            'status' => TimeEntryStatus::Pending,
        ];
    }

    /**
     * Associates the entry with a timesheet user and their organization.
     *
     * @param  User  $user  Employee who logged the entry.
     * @return static
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
        ]);
    }

    /**
     * Marks the entry as approved and optionally linked to QuickBooks.
     *
     * @param  string|null  $qboId  QuickBooks time activity identifier.
     * @return static
     */
    public function approved(?string $qboId = '100'): static
    {
        return $this->state(fn (): array => [
            'status' => TimeEntryStatus::Approved,
            'qbo_id' => $qboId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Marks the entry as rejected by a supervisor.
     *
     * @param  string|null  $reason  Optional rejection reason.
     * @return static
     */
    public function rejected(?string $reason = 'Incorrect project'): static
    {
        return $this->state(fn (): array => [
            'status' => TimeEntryStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
        ]);
    }
}
