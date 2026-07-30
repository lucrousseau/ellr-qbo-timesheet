<?php

use App\Http\Controllers\Api\HealthController;
use App\Support\PasswordPolicy;

covers(HealthController::class);

it('returns api health status', function () {
    config([
        'app.require_email_verification' => true,
        'quickbooks.time_tracker_max_accumulated_seconds' => 86400,
    ]);

    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'service' => 'ellr-qbo-timesheet-api',
            'require_email_verification' => true,
            'time_tracker_max_accumulated_seconds' => 86400,
            'password_policy' => [
                'loaded' => true,
                'min_length' => PasswordPolicy::config()['minLength'],
            ],
        ]);

    expect($response->json('require_email_verification'))->toBeBool();
    expect($response->json('time_tracker_max_accumulated_seconds'))->toBeInt();
});

it('returns require_email_verification as false when disabled', function () {
    config(['app.require_email_verification' => false]);

    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertJsonPath('require_email_verification', false);

    expect($response->json('require_email_verification'))->toBeBool();
});

it('casts disabled require_email_verification config values to booleans', function () {
    config(['app.require_email_verification' => 0]);

    $response = $this->getJson('/api/health');

    $response->assertOk();

    expect($response->json('require_email_verification'))->toBeFalse();
});
