<?php

/**
 * External ticket tracker integration settings (Jira / Linear).
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Ticket integrations
    |--------------------------------------------------------------------------
    |
    | Feature gates for searching external tickets while logging time.
    | Credentials and OAuth connect flows are added in a follow-up; until then
    | search endpoints return integration_disabled when a provider is off.
    |
    */

    'jira' => [
        'enabled' => (bool) env('INTEGRATIONS_JIRA_ENABLED', false),
        // Scaffold only: stays false until OAuth connect stores organization credentials.
        'connected' => (bool) env('INTEGRATIONS_JIRA_CONNECTED', false),
        'base_url' => env('INTEGRATIONS_JIRA_BASE_URL'),
    ],

    'linear' => [
        'enabled' => (bool) env('INTEGRATIONS_LINEAR_ENABLED', false),
        // Scaffold only: stays false until OAuth connect stores organization credentials.
        'connected' => (bool) env('INTEGRATIONS_LINEAR_CONNECTED', false),
        'base_url' => env('INTEGRATIONS_LINEAR_BASE_URL', 'https://linear.app'),
    ],
];
