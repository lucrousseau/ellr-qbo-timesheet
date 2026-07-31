<?php

/**
 * Lookback window configuration for realm-wide time activity reconcile scans.
 */

namespace App\Support;

use App\Enums\TimeActivityReconcileScope;
use App\Models\QboRealmSyncState;

/**
 * Resolves configured TxnDate windows and scheduled reconcile skip rules.
 */
class TimeActivityReconcileLookback
{
    /**
     * @param  TimeActivityReconcileScope  $scope  Recent limits to the smallest lookback step.
     * @return list<int>
     */
    public static function stepsForScope(TimeActivityReconcileScope $scope): array
    {
        $steps = self::normalizedSteps();

        if ($scope === TimeActivityReconcileScope::Recent && $steps !== []) {
            return [$steps[0]];
        }

        return $steps;
    }

    /**
     * @return list<int>
     */
    public static function normalizedSteps(): array
    {
        $configured = config('quickbooks.time_activities_lookback_steps');
        $steps = is_array($configured) ? $configured : [14, 30, 90]; // @pest-mutate-ignore default lookback ladder when config is missing
        $maxLookback = (int) config('quickbooks.time_activities_lookback_days', 90); // @pest-mutate-ignore
        $normalized = [];

        foreach ($steps as $step) {
            $days = (int) $step;

            if ($days > 0 && $days <= $maxLookback) {
                $normalized[$days] = $days;
            }
        }

        if ($normalized === []) {
            $normalized[$maxLookback] = $maxLookback;
        }

        $values = array_values($normalized);
        sort($values);

        return $values;
    }

    /**
     * Returns whether a scheduled reconcile can be skipped for a recently synced realm.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @return bool
     */
    public static function shouldSkipScheduledReconcile(string $realmId): bool
    {
        $skipHours = (int) config('quickbooks.time_activities_reconcile_skip_hours', 1); // @pest-mutate-ignore scheduled reconcile skip window

        if ($skipHours <= 0) {
            return false;
        }

        $state = QboRealmSyncState::query()->where('realm_id', $realmId)->first();

        if ($state === null) {
            return false;
        }

        $threshold = now()->subHours($skipHours);

        if ($state->last_webhook_at !== null && $state->last_webhook_at->gte($threshold)) {
            return true;
        }

        return $state->last_reconciled_at !== null && $state->last_reconciled_at->gte($threshold);
    }
}
