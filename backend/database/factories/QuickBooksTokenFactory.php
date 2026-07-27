<?php

namespace Database\Factories;

use App\Models\QuickBooksToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuickBooksToken>
 */
class QuickBooksTokenFactory extends Factory
{
    protected $model = QuickBooksToken::class;

    public function definition(): array
    {
        return [
            'user_id' => 1,
            'realm_id' => (string) fake()->unique()->numerify('##########'),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'access_token_expires_at' => now()->addHour(),
            'refresh_token_expires_at' => now()->addDays(100),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'access_token_expires_at' => now()->subMinute(),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
        ]);
    }
}
