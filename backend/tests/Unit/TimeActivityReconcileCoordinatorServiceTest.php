<?php

/**
 * Tests for time activity reconcile orchestration.
 */

use App\Enums\TimeActivityReconcileScope;
use App\Jobs\ReconcileRealmTimeActivitiesJob;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\TimeActivityReconcileCoordinatorService;
use App\Services\TimeActivitySnapshotService;
use App\Services\TimeActivitySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

covers(TimeActivityReconcileCoordinatorService::class);

uses(RefreshDatabase::class);

it('queues a full backfill when snapshots are missing', function () {
    Queue::fake();

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldNotReceive('reconcileRealm');

    $coordinator = new TimeActivityReconcileCoordinatorService(
        app(TimeActivitySnapshotService::class),
        $sync,
    );

    $coordinator->prepareRealmForList($token, refresh: false);

    Queue::assertPushed(ReconcileRealmTimeActivitiesJob::class, fn (ReconcileRealmTimeActivitiesJob $job) => $job->tokenId === $token->id
        && $job->fullLookback === true);
});

it('reconciles every lookback window inline when refresh is requested', function () {
    Queue::fake();

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('reconcileRealm')
        ->once()
        ->with($token, TimeActivityReconcileScope::Full);

    $coordinator = new TimeActivityReconcileCoordinatorService(
        app(TimeActivitySnapshotService::class),
        $sync,
    );

    $coordinator->prepareRealmForList($token, refresh: true);

    Queue::assertNothingPushed();
});
