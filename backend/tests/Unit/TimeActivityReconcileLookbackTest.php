<?php

/**
 * Tests for reconcile lookback window resolution.
 */

use App\Enums\TimeActivityReconcileScope;
use App\Models\QboRealmSyncState;
use App\Support\TimeActivityReconcileLookback;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(TimeActivityReconcileLookback::class);

uses(RefreshDatabase::class);

it('returns only the smallest step for recent reconcile scope', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [14, 30, 90],
        'quickbooks.time_activities_lookback_days' => 90,
    ]);

    expect(TimeActivityReconcileLookback::stepsForScope(TimeActivityReconcileScope::Recent))->toBe([14])
        ->and(TimeActivityReconcileLookback::stepsForScope(TimeActivityReconcileScope::Full))->toBe([14, 30, 90]);
});

it('falls back to max lookback when configured steps are invalid', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => 'invalid',
        'quickbooks.time_activities_lookback_days' => 45,
    ]);

    expect(TimeActivityReconcileLookback::normalizedSteps())->toBe([14, 30]);
});

it('skips scheduled reconcile for recently webhook-synced realms', function () {
    config(['quickbooks.time_activities_reconcile_skip_hours' => 2]);

    QboRealmSyncState::query()->create([
        'realm_id' => 'realm-recent',
        'last_webhook_at' => now()->subHour(),
    ]);

    expect(TimeActivityReconcileLookback::shouldSkipScheduledReconcile('realm-recent'))->toBeTrue()
        ->and(TimeActivityReconcileLookback::shouldSkipScheduledReconcile('realm-missing'))->toBeFalse();
});

it('does not skip scheduled reconcile when skip hours is disabled', function () {
    config(['quickbooks.time_activities_reconcile_skip_hours' => 0]);

    QboRealmSyncState::query()->create([
        'realm_id' => 'realm-recent',
        'last_reconciled_at' => now(),
    ]);

    expect(TimeActivityReconcileLookback::shouldSkipScheduledReconcile('realm-recent'))->toBeFalse();
});
