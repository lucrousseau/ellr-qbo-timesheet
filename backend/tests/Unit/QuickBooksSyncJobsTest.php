<?php

use App\Enums\TimeActivityReconcileScope;
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

it('logs permanent webhook job failures when no exception is provided', function () {
    Log::shouldReceive('error')
        ->once()
        ->with('QuickBooks webhook job failed permanently', [
            'message' => null,
        ]);

    (new ProcessQuickBooksWebhookJob([]))->failed(null);
});

it('deduplicates identical webhook payloads in the queue', function () {
    $payload = [
        'eventNotifications' => [
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '1', 'operation' => 'Update'],
                    ],
                ],
            ],
        ],
    ];

    $job = new ProcessQuickBooksWebhookJob($payload);

    expect($job->uniqueId())->toBe('quickbooks:webhook:'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)))
        ->and($job->uniqueFor)->toBe(300);
});

it('reconciles a realm when the token still exists', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('reconcileRealm')
        ->once()
        ->with(
            Mockery::on(fn (QuickBooksToken $resolved) => $resolved->is($token)),
            TimeActivityReconcileScope::Full,
        );

    (new ReconcileRealmTimeActivitiesJob($token->id))->handle($sync);
});

it('deduplicates reconcile jobs per realm and lookback mode', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-42']);
    $job = new ReconcileRealmTimeActivitiesJob($token->id, fullLookback: false);

    expect($job->uniqueId())->toBe('quickbooks:reconcile:realm-42:recent')
        ->and($job->uniqueFor)->toBe(300);
});

it('falls back to the token id when the realm cannot be resolved for deduplication', function () {
    $job = new ReconcileRealmTimeActivitiesJob(999, fullLookback: true);

    expect($job->uniqueId())->toBe('quickbooks:reconcile:999:full');
});

it('skips reconcile when the token was removed before the job runs', function () {
    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldNotReceive('reconcileRealm');

    (new ReconcileRealmTimeActivitiesJob(999))->handle($sync);
});
