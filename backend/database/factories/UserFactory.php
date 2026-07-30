<?php

/**
 * Model factory for generating User records in tests and seeders.
 */

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(PasswordPolicy::validTestPassword()),
            'locale' => 'en',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Persist users with a tenant organization when none was supplied.
     *
     * Laravel has no pre-insert factory hook; this override assigns organization_id before store().
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes  Factory attribute overrides.
     * @param  Model|null  $parent  Parent model for nested factory creation.
     * @return User|Collection<int, User>
     */
    public function create($attributes = [], ?Model $parent = null): User|Collection
    {
        if (! empty($attributes)) {
            return $this->state($attributes)->create([], $parent);
        }

        $results = $this->make($attributes, $parent);

        if ($results instanceof User) {
            $this->assignOrganizationIfMissing($results);
            $this->store(new Collection([$results]));
            $this->callAfterCreating(new Collection([$results]), $parent);
        } else {
            $results->each(fn (User $user) => $this->assignOrganizationIfMissing($user));
            $this->store($results);
            $this->callAfterCreating($results, $parent);
        }

        return $results;
    }

    /**
     * Assigns a tenant organization before persisting users without organization_id.
     *
     * @param  User  $user  User instance about to be stored.
     * @return void
     */
    private function assignOrganizationIfMissing(User $user): void
    {
        if ($user->organization_id !== null) {
            return;
        }

        $user->organization_id = Organization::factory()->create()->id;
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an administrator.
     *
     * @return static
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    /**
     * Indicate that the user is a platform super administrator.
     *
     * @return static
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
            'is_super_admin' => true,
        ]);
    }
}
