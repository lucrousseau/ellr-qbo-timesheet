<?php

use App\Jobs\ProcessQuickBooksWebhookJob;
use App\Jobs\ReconcileRealmTimeActivitiesJob;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\TimeActivitySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

covers(ProcessQuickBooksWebhookJob::class);
covers(ReconcileRealmTimeActivitiesJob::class);

uses(RefreshDatabase::class);

it('logs permanent webhook job failures', function () {
    Log::shouldReceive('error')
        ->once()
        ->with('QuickBooks webhook job failed permanently', [
            'message' => 'queue failed',
        ]);

    (new ProcessQuickBooksWebhookJob([]))->failed(new RuntimeException('queue failed'));
});

it('reconciles a realm when the token still exists', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('reconcileRealm')
        ->once()
        ->with(Mockery::on(fn (QuickBooksToken $resolved) => $resolved->is($token)));

    (new ReconcileRealmTimeActivitiesJob($token->id))->handle($sync);
});

it('skips reconcile when the token was removed before the job runs', function () {
    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldNotReceive('reconcileRealm');

    (new ReconcileRealmTimeActivitiesJob(999))->handle($sync);
});
