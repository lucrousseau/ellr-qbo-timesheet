<?php

use App\Http\Controllers\Api\UserPreferencesController;
use App\Models\Organization;
use App\Models\User;

covers(UserPreferencesController::class);

it('updates locale and timezone preferences together', function () {
    $organization = Organization::factory()->create(['company_timezone' => 'America/Toronto']);
    $user = User::factory()->for($organization)->create([
        'locale' => 'en',
        'timezone' => null,
    ]);

    $this->actingAs($user)
        ->patchJson('/api/user/preferences', [
            'locale' => 'fr',
            'timezone' => 'America/Toronto',
        ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.locale', 'fr')
        ->assertJsonPath('user.timezone', 'America/Toronto');
});

it('requires authentication for combined preferences', function () {
    $this->patchJson('/api/user/preferences', [
        'locale' => 'en',
        'timezone' => null,
    ])->assertUnauthorized();
});
