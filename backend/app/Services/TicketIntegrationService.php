<?php

/**
 * Resolves ticket integration availability and search scaffolding.
 */

namespace App\Services;

/**
 * Reports Jira/Linear enablement and stubs issue search until OAuth ships.
 */
class TicketIntegrationService
{
    /**
     * Returns public status for each ticket provider.
     *
     * @return array{jira: array{enabled: bool, connected: bool}, linear: array{enabled: bool, connected: bool}}
     */
    public function status(): array
    {
        return [
            'jira' => [
                'enabled' => (bool) config('integrations.jira.enabled'),
                'connected' => false,
            ],
            'linear' => [
                'enabled' => (bool) config('integrations.linear.enabled'),
                'connected' => false,
            ],
        ];
    }

    /**
     * Indicates whether any ticket provider can power the timer picker.
     *
     * @return bool
     */
    public function pickerAvailable(): bool
    {
        $status = $this->status();

        return ($status['jira']['enabled'] && $status['jira']['connected'])
            || ($status['linear']['enabled'] && $status['linear']['connected']);
    }

    /**
     * Searches external tickets for the timer picker.
     *
     * Scaffold only: aborts with integration_disabled until provider OAuth is configured.
     *
     * @param  string  $query  Free-text ticket search.
     * @param  string|null  $provider  Optional provider filter (`jira` or `linear`).
     * @return never
     */
    public function search(string $query, ?string $provider = null): never
    {
        unset($query, $provider);

        abort(response()->json([
            'error' => 'integration_disabled',
            'message' => __('api.integration_disabled'),
        ], 503));
    }
}
