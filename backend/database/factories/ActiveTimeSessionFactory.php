<?php

/**
 * Model factory for generating ActiveTimeSession records in tests.
 */

namespace Database\Factories;

use App\Models\ActiveTimeSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActiveTimeSession>
 */
class ActiveTimeSessionFactory extends Factory
{
    protected $model = ActiveTimeSession::class;

    /**
     * Default factory state for an active time session.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_ref' => '1',
            'project_ref' => null,
            'service_ref' => null,
            'description' => null,
            'ticket_key' => null,
            'ticket_source' => null,
            'ticket_url' => null,
            'ticket_title' => null,
            'is_billable' => false,
            'accumulated_seconds' => 0,
            'running_since' => now(),
        ];
    }
}
