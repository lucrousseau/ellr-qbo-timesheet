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
                'enabled' => (bool) config('integrations.jira.enabled'), // @pest-mutate-ignore config bool gate
                'connected' => (bool) config('integrations.jira.connected'), // @pest-mutate-ignore config bool gate
            ],
            'linear' => [
                'enabled' => (bool) config('integrations.linear.enabled'), // @pest-mutate-ignore config bool gate
                'connected' => (bool) config('integrations.linear.connected'), // @pest-mutate-ignore config bool gate
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
            'error' => 'integration_disabled', // @pest-mutate-ignore scaffold error payload
            'message' => __('api.integration_disabled'), // @pest-mutate-ignore scaffold error payload
        ], 503)); // @pest-mutate-ignore scaffold error payload
    }
}
