<?php

use App\Http\Middleware\EnsureEmailVerifiedIfRequired;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Route;

covers(EnsureUserIsAdmin::class);
covers(EnsureEmailVerifiedIfRequired::class);

beforeEach(function () {
    Route::middleware('admin')->get('/_test/admin', fn () => response()->json(['ok' => true]));
    Route::middleware('verified.email')->get('/_test/verified', fn () => response()->json(['ok' => true]));
});

it('rejects unauthenticated users for admin routes', function () {
    $this->getJson('/_test/admin')
        ->assertForbidden()
        ->assertJsonPath('error', 'admin_required');
});

it('rejects non-admin users for admin routes', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/_test/admin')
        ->assertForbidden()
        ->assertJsonPath('error', 'admin_required');
});

it('allows administrators through admin middleware', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson('/_test/admin')
        ->assertOk();
});

it('allows unverified users when email verification is not required', function () {
    config(['app.require_email_verification' => false]);

    $this->actingAs(User::factory()->unverified()->create())
        ->getJson('/_test/verified')
        ->assertOk();
});

it('rejects unverified users when email verification is required', function () {
    config(['app.require_email_verification' => true]);

    $this->actingAs(User::factory()->unverified()->create())
        ->getJson('/_test/verified')
        ->assertForbidden()
        ->assertJsonPath('error', 'email_not_verified');
});

it('allows verified users when email verification is required', function () {
    config(['app.require_email_verification' => true]);

    $this->actingAs(User::factory()->create())
        ->getJson('/_test/verified')
        ->assertOk();
});
