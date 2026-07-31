<?php

use App\Console\Commands\ReconcileTimeActivitiesCommand;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\TimeActivitySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(ReconcileTimeActivitiesCommand::class);

uses(RefreshDatabase::class);

it('runs a full reconcile when invoked manually', function () {
    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create();

    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('reconcileAllRealms')
        ->once()
        ->with(false)
        ->andReturn(3);
    app()->instance(TimeActivitySyncService::class, $sync);

    $this->artisan('quickbooks:reconcile-time-activities')
        ->expectsOutputToContain('Reconciled 3 time activity snapshot(s).')
        ->assertSuccessful();
});

it('runs lightweight scheduled reconcile when the scheduled option is passed', function () {
    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create();

    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('reconcileAllRealms')
        ->once()
        ->with(true)
        ->andReturn(1);
    app()->instance(TimeActivitySyncService::class, $sync);

    $this->artisan('quickbooks:reconcile-time-activities --scheduled')
        ->expectsOutputToContain('Reconciled 1 time activity snapshot(s).')
        ->assertSuccessful();
});
