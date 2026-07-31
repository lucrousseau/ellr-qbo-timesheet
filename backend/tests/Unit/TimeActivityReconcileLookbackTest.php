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

it('skips scheduled reconcile when the realm was reconciled recently', function () {
    config(['quickbooks.time_activities_reconcile_skip_hours' => 2]);

    QboRealmSyncState::query()->create([
        'realm_id' => 'realm-reconciled',
        'last_reconciled_at' => now()->subMinutes(30),
    ]);

    expect(TimeActivityReconcileLookback::shouldSkipScheduledReconcile('realm-reconciled'))->toBeTrue();
});

it('keeps only lookback steps within the configured maximum', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [7, 14, 45, 120],
        'quickbooks.time_activities_lookback_days' => 30,
    ]);

    expect(TimeActivityReconcileLookback::normalizedSteps())->toBe([7, 14]);
});

it('uses the configured maximum when every step is filtered out', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [120, 200],
        'quickbooks.time_activities_lookback_days' => 60,
    ]);

    expect(TimeActivityReconcileLookback::normalizedSteps())->toBe([60]);
});

it('returns the configured maximum when recent scope has no valid steps', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [0, -3],
        'quickbooks.time_activities_lookback_days' => 21,
    ]);

    expect(TimeActivityReconcileLookback::stepsForScope(TimeActivityReconcileScope::Recent))->toBe([21]);
});

it('normalizes string step values and sorts lookback windows', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => ['30', '7', '14'],
        'quickbooks.time_activities_lookback_days' => 90,
    ]);

    expect(TimeActivityReconcileLookback::normalizedSteps())->toBe([7, 14, 30]);
});

it('includes lookback steps that match the configured maximum exactly', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [7, 30],
        'quickbooks.time_activities_lookback_days' => 30,
    ]);

    expect(TimeActivityReconcileLookback::normalizedSteps())->toBe([7, 30]);
});

it('does not skip scheduled reconcile when skip hours is disabled', function () {
    config(['quickbooks.time_activities_reconcile_skip_hours' => 0]);

    QboRealmSyncState::query()->create([
        'realm_id' => 'realm-recent',
        'last_webhook_at' => now()->subMinute(),
    ]);

    expect(TimeActivityReconcileLookback::shouldSkipScheduledReconcile('realm-recent'))->toBeFalse();
});

it('does not skip scheduled reconcile when sync timestamps are stale', function () {
    config(['quickbooks.time_activities_reconcile_skip_hours' => 2]);

    QboRealmSyncState::query()->create([
        'realm_id' => 'realm-stale',
        'last_webhook_at' => now()->subHours(5),
        'last_reconciled_at' => now()->subHours(5),
    ]);

    expect(TimeActivityReconcileLookback::shouldSkipScheduledReconcile('realm-stale'))->toBeFalse();
});

it('does not skip scheduled reconcile when only stale webhook activity exists', function () {
    config(['quickbooks.time_activities_reconcile_skip_hours' => 2]);

    QboRealmSyncState::query()->create([
        'realm_id' => 'realm-webhook-only',
        'last_webhook_at' => now()->subHours(5),
    ]);

    expect(TimeActivityReconcileLookback::shouldSkipScheduledReconcile('realm-webhook-only'))->toBeFalse();
});
