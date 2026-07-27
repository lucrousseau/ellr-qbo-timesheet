<?php

use App\Http\Controllers\Api\AuthController;
use App\Models\User;

covers(AuthController::class);

it('registers a new user', function () {
    $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], frontendHeaders())
        ->assertCreated()
        ->assertJsonPath('user.email', 'jane@example.com');

    $this->assertAuthenticated();
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);

    $this->assertAuthenticatedAs($user);
});

it('regenerates the session id after login', function () {
    $user = User::factory()->create(['password' => 'password']);
    $sessionBefore = session()->getId();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ], frontendHeaders())->assertOk();

    expect(session()->getId())->not->toBe($sessionBefore);
});

it('regenerates the session id after registration', function () {
    $sessionBefore = session()->getId();

    $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], frontendHeaders())->assertCreated();

    expect(session()->getId())->not->toBe($sessionBefore);
});

it('validates login payload', function () {
    $this->postJson('/api/login', [], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('rejects invalid login credentials', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'password']);

    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ], frontendHeaders())
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid credentials.');
});

it('requires email on login', function () {
    $this->postJson('/api/login', [
        'password' => 'password',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('requires password on login', function () {
    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('rejects invalid email format on login', function () {
    $this->postJson('/api/login', [
        'email' => 'not-an-email',
        'password' => 'password',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rate limits login attempts', function () {
    $user = User::factory()->create(['password' => 'password']);

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ], frontendHeaders());
    }

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ], frontendHeaders())->assertStatus(429);
});

it('rejects registration when disabled', function () {
    config(['app.allow_registration' => false]);

    $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'registration_disabled')
        ->assertJsonPath('message', 'Registration disabled.');

    $this->assertGuest();
});

it('validates registration payload', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/register', [
        'name' => '',
        'email' => 'taken@example.com',
        'password' => 'short',
        'password_confirmation' => 'mismatch',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('requires name on registration', function () {
    $this->postJson('/api/register', [
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('requires email on registration', function () {
    $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('requires password on registration', function () {
    $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('rejects invalid email format on registration', function () {
    $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'not-an-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects duplicate email on registration', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'taken@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects unconfirmed password on registration', function () {
    $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'different',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('rejects overly long names on registration', function () {
    $this->postJson('/api/register', [
        'name' => str_repeat('a', 256),
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('requires a string password on login', function () {
    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => ['not-a-string'],
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);
});

it('logs out the authenticated user', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ], frontendHeaders())->assertOk();

    $this->postJson('/api/logout', [], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('message', 'Logged out.');
});

it('requires authentication for protected routes', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});
