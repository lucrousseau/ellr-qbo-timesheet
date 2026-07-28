<?php

use App\Http\Middleware\SetLocaleFromUser;
use App\Models\User;

covers(SetLocaleFromUser::class);

it('applies the authenticated user locale to json api messages', function () {
    $user = User::factory()->create(['locale' => 'fr']);

    $this->actingAs($user)
        ->postJson('/api/logout', [], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('message', __('api.logged_out', [], 'fr'));
});

it('falls back to english for unsupported user locales', function () {
    $user = User::factory()->create(['locale' => 'de']);

    $this->actingAs($user)
        ->postJson('/api/logout', [], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('message', __('api.logged_out', [], 'en'));
});

it('keeps english for unauthenticated api requests', function () {
    $this->postJson('/api/login', [
        'email' => 'missing@example.com',
        'password' => 'wrong-password',
    ], frontendHeaders())
        ->assertUnauthorized()
        ->assertJsonPath('message', __('api.invalid_credentials', [], 'en'));
});
