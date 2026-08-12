<?php

/**
 * Model factory for generating Organization records in tests and seeders.
 */

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'realm_id' => null,
            // Avoid accidental QuickBooks CompanyInfo calls from syncIfMissing in unit tests.
            'company_timezone' => 'UTC',
        ];
    }

    /**
     * Binds the organization to a QuickBooks company realm.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @return static
     */
    public function withRealm(string $realmId): static
    {
        return $this->state(fn (array $attributes) => [
            'realm_id' => $realmId,
        ]);
    }
}
