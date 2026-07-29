<?php

/**
 * Model factory for time activity snapshot rows in tests.
 */

namespace Database\Factories;

use App\Models\TimeActivitySnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeActivitySnapshot>
 */
class TimeActivitySnapshotFactory extends Factory
{
    protected $model = TimeActivitySnapshot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-30 days', '-1 day');
        $end = (clone $start)->modify('+1 hour');

        return [
            'realm_id' => (string) fake()->numerify('##########'),
            'qbo_id' => (string) fake()->unique()->numerify('####'),
            'qbo_employee_ref' => (string) fake()->numerify('###'),
            'customer_ref' => null,
            'customer_name' => null,
            'project_ref' => null,
            'project_name' => null,
            'item_ref' => null,
            'item_name' => null,
            'start_time' => $start,
            'end_time' => $end,
            'txn_date' => $start->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
            'billable_status' => fake()->randomElement(['Billable', 'NotBillable']),
            'qbo_sync_token' => '0',
            'qbo_last_updated_at' => now(),
            'last_synced_at' => now(),
        ];
    }

    /**
     * Scopes the snapshot to one QuickBooks company realm.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @return static
     */
    public function forRealm(string $realmId): static
    {
        return $this->state(fn () => ['realm_id' => $realmId]);
    }

    /**
     * Scopes the snapshot to one QuickBooks employee reference.
     *
     * @param  string  $employeeRef  QuickBooks employee reference.
     * @return static
     */
    public function forEmployee(string $employeeRef): static
    {
        return $this->state(fn () => ['qbo_employee_ref' => $employeeRef]);
    }
}
