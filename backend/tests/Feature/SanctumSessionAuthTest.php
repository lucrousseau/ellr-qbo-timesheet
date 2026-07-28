<?php

use App\Http\Controllers\Api\AuthController;
use App\Models\User;

covers(AuthController::class);

it('completes login after csrf priming and returns the authenticated user', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->get('/sanctum/csrf-cookie', frontendHeaders())->assertNoContent();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);

    $this->getJson('/api/user', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.email', $user->email);
});
