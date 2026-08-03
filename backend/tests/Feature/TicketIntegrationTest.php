<?php

/**
 * Feature tests for ticket integration status and search scaffolding.
 */

use App\Http\Controllers\Api\TicketIntegrationController;

covers(TicketIntegrationController::class);

it('returns ticket integration status for authenticated users', function () {
    config([
        'integrations.jira.enabled' => true,
        'integrations.linear.enabled' => false,
    ]);
    actingAsWithQboEmployee();

    $this->getJson('/api/integrations/tickets/status', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.jira.enabled', true)
        ->assertJsonPath('data.jira.connected', false)
        ->assertJsonPath('data.linear.enabled', false)
        ->assertJsonPath('data.picker_available', false);
});

it('returns integration_disabled when searching tickets without oauth', function () {
    actingAsWithQboEmployee();

    $this->getJson('/api/integrations/tickets?q=PROJ', frontendHeaders())
        ->assertStatus(503)
        ->assertJsonPath('error', 'integration_disabled');
});
