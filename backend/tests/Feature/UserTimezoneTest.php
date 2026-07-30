<?php

use App\Http\Controllers\Api\UserTimezoneController;
use App\Models\Organization;
use App\Models\User;

covers(UserTimezoneController::class);

it('returns null timezone and effective display timezone on the authenticated user', function () {
    $organization = Organization::factory()->create(['company_timezone' => 'America/Toronto']);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->getJson('/api/user', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.timezone', null)
        ->assertJsonPath('user.effective_display_timezone', 'America/Toronto');
});

it('updates the authenticated user timezone preference', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/user/timezone', ['timezone' => 'America/Toronto'], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.timezone', 'America/Toronto')
        ->assertJsonPath('user.effective_display_timezone', 'America/Toronto');

    expect($user->fresh()->timezone)->toBe('America/Toronto');
});

it('clears the user timezone preference when null is submitted', function () {
    $user = User::factory()->create(['timezone' => 'America/Toronto']);

    $this->actingAs($user)
        ->patchJson('/api/user/timezone', ['timezone' => null], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.timezone', null);

    expect($user->fresh()->timezone)->toBeNull();
});

it('validates the timezone payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/user/timezone', ['timezone' => 'Not/A_Timezone'], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['timezone']);
});

it('requires authentication to update timezone', function () {
    $this->patchJson('/api/user/timezone', ['timezone' => 'America/Toronto'], frontendHeaders())
        ->assertUnauthorized();
});
