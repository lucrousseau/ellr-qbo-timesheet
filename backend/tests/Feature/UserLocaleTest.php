<?php

use App\Http\Controllers\Api\UserLocaleController;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

covers(UserLocaleController::class);

it('returns the default english locale on the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/user', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.locale', 'en');
});

it('updates the authenticated user locale preference', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/user/locale', ['locale' => 'fr'], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.locale', 'fr');

    expect($user->fresh()->locale)->toBe('fr');
});

it('allows non-admin users to update their own locale', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->patchJson('/api/user/locale', ['locale' => 'fr'], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.locale', 'fr');
});

it('validates the locale payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/user/locale', ['locale' => 'de'], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['locale']);
});

it('requires authentication to update locale', function () {
    $this->patchJson('/api/user/locale', ['locale' => 'fr'], frontendHeaders())
        ->assertUnauthorized();
});

it('returns french api messages when the user locale is french', function () {
    $user = User::factory()->create(['locale' => 'fr', 'is_admin' => false]);

    $this->actingAs($user)
        ->getJson('/api/quickbooks/status', frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('message', __('api.admin_required', [], 'fr'));
});

it('sends verification notifications using the user locale', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create(['locale' => 'fr']);
    $user->sendEmailVerificationNotification();

    Notification::assertSentTo(
        $user,
        VerifyEmailNotification::class,
        fn (VerifyEmailNotification $notification): bool => $notification->locale === 'fr',
    );
});

it('returns french validation errors when the user locale is french', function () {
    $user = User::factory()->create(['locale' => 'fr']);

    $response = $this->actingAs($user)
        ->patchJson('/api/user/locale', ['locale' => 'de'], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['locale']);

    expect($response->json('errors.locale.0'))->toBe(__('validation.in', ['attribute' => 'locale'], 'fr'));
});
