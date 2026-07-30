<?php

use App\Models\QboRealmSyncState;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksWebhookProcessorService;
use App\Services\TimeActivitySnapshotService;
use App\Services\TimeActivitySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

covers(QuickBooksWebhookProcessorService::class);

uses(RefreshDatabase::class);

it('ignores invalid top-level eventNotifications payloads', function () {
    Log::spy();

    app(QuickBooksWebhookProcessorService::class)->process([
        'eventNotifications' => 'not-an-array',
    ]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('QuickBooks webhook ignored invalid eventNotifications payload');
});

it('ignores malformed notifications and time activities without ids', function () {
    Log::spy();
    $user = User::factory()->create();
    $user->organization->update(['realm_id' => 'realm-1']);
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-1']);

    app(QuickBooksWebhookProcessorService::class)->process([
        'eventNotifications' => [
            'not-an-array',
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        'not-an-array',
                        ['name' => 'TimeActivity', 'operation' => 'Update'],
                    ],
                ],
            ],
        ],
    ]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('QuickBooks webhook ignored TimeActivity without id', ['realm_id' => 'realm-1']);
});

it('updates webhook sync state after processing entities', function () {
    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('syncOneById')
        ->once()
        ->with(
            Mockery::type(QuickBooksToken::class),
            '9',
            false,
        );

    $processor = new QuickBooksWebhookProcessorService(
        $sync,
        app(TimeActivitySnapshotService::class),
    );

    $user = User::factory()->create();
    $user->organization->update(['realm_id' => 'realm-1']);
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-1']);

    $processor->process([
        'eventNotifications' => [
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '9', 'operation' => 'Update'],
                    ],
                ],
            ],
        ],
    ]);

    $state = QboRealmSyncState::query()->where('realm_id', 'realm-1')->first();

    expect($state)->not->toBeNull()
        ->and($state->last_webhook_at)->not->toBeNull();
});

it('calls sync for create webhooks without resolving missing names', function () {
    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('syncOneById')
        ->once()
        ->with(
            Mockery::type(QuickBooksToken::class),
            '55',
            false,
        );

    $user = User::factory()->create();
    $user->organization->update(['realm_id' => 'realm-1']);
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-1']);

    (new QuickBooksWebhookProcessorService($sync, app(TimeActivitySnapshotService::class)))->process([
        'eventNotifications' => [
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '55', 'operation' => 'Create'],
                    ],
                ],
            ],
        ],
    ]);
});

it('logs when notifications omit realm ids or entity lists', function () {
    Log::spy();
    $user = User::factory()->create();
    $user->organization->update(['realm_id' => 'realm-1']);

    app(QuickBooksWebhookProcessorService::class)->process([
        'eventNotifications' => [
            ['dataChangeEvent' => ['entities' => [['name' => 'TimeActivity', 'id' => '1']]]],
            ['realmId' => 'realm-1', 'dataChangeEvent' => []],
        ],
    ]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('QuickBooks webhook ignored notification without realmId');
    Log::shouldHaveReceived('debug')
        ->once()
        ->with('QuickBooks webhook ignored notification without entities', ['realm_id' => 'realm-1']);
});

it('processes numeric webhook identifiers as strings', function () {
    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('syncOneById')
        ->once()
        ->with(Mockery::type(QuickBooksToken::class), '55', false);

    $user = User::factory()->create();
    $user->organization->update(['realm_id' => '1']);
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => '1']);

    (new QuickBooksWebhookProcessorService($sync, app(TimeActivitySnapshotService::class)))->process([
        'eventNotifications' => [
            [
                'realmId' => 1,
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => 55, 'operation' => 'UPDATE'],
                    ],
                ],
            ],
        ],
    ]);
});

it('defaults missing webhook operations to sync and skips other entities', function () {
    Log::spy();
    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('syncOneById')
        ->once()
        ->with(Mockery::type(QuickBooksToken::class), '77', false);

    $user = User::factory()->create();
    $user->organization->update(['realm_id' => 'realm-1']);
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-1']);

    (new QuickBooksWebhookProcessorService($sync, app(TimeActivitySnapshotService::class)))->process([
        'eventNotifications' => [
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'Customer', 'id' => '1', 'operation' => 'Update'],
                        ['name' => 'TimeActivity', 'id' => '77'],
                    ],
                ],
            ],
        ],
    ]);

    Log::shouldHaveReceived('debug')
        ->once()
        ->with('QuickBooks webhook skipped non-time-activity entity', [
            'realm_id' => 'realm-1',
            'entity' => 'Customer',
        ]);
});

it('soft-deletes snapshots for delete and void webhook operations', function () {
    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('syncOneById')->never();

    $snapshots = Mockery::mock(TimeActivitySnapshotService::class);
    $snapshots->shouldReceive('softDeleteByQboId')
        ->once()
        ->with('realm-1', '33');

    $user = User::factory()->create();
    $user->organization->update(['realm_id' => 'realm-1']);
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-1']);

    (new QuickBooksWebhookProcessorService($sync, $snapshots))->process([
        'eventNotifications' => [
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '33', 'operation' => 'VOID'],
                    ],
                ],
            ],
        ],
    ]);
});

it('logs when webhook notifications reference unclaimed realms', function () {
    Log::spy();

    app(QuickBooksWebhookProcessorService::class)->process([
        'eventNotifications' => [
            [
                'realmId' => 'missing-realm',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '1', 'operation' => 'Update'],
                    ],
                ],
            ],
        ],
    ]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('QuickBooks webhook ignored unclaimed realm', ['realm_id' => 'missing-realm']);
});

it('ignores payloads without an eventNotifications key', function () {
    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('syncOneById')->never();

    (new QuickBooksWebhookProcessorService($sync, app(TimeActivitySnapshotService::class)))->process([]);
});
