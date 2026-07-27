<?php

use App\Http\Controllers\Api\HealthController;

covers(HealthController::class);

it('returns api health status', function () {
    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'service' => 'ellr-qbo-timesheet-api',
            'require_email_verification' => false,
        ]);
});
