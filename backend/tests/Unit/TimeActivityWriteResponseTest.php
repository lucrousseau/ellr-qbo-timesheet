<?php

/**
 * Tests for QuickBooks write response snapshot helpers.
 */

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\TimeActivitySnapshotService;
use App\Services\TimeActivitySyncService;
use App\Support\TimeActivityWriteResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(TimeActivityWriteResponse::class);

uses(RefreshDatabase::class);

it('merges sparse write payloads onto loaded quickbooks entities', function () {
    $existing = (object) [
        'Id' => '12',
        'SyncToken' => '1',
        'EmployeeRef' => (object) ['value' => '7'],
        'Description' => 'Before',
    ];
    $written = (object) [
        'Id' => '12',
        'SyncToken' => '2',
        'Description' => 'After',
    ];

    $merged = TimeActivityWriteResponse::merge($existing, $written);

    expect($merged->SyncToken)->toBe('2')
        ->and($merged->Description)->toBe('After')
        ->and($merged->EmployeeRef->value)->toBe('7');
});

it('upserts snapshots from write responses and falls back to syncOneById', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $snapshots = Mockery::mock(TimeActivitySnapshotService::class);
    $snapshots->shouldReceive('upsertFromQboEntity')
        ->once()
        ->with($token->realm_id, Mockery::type('object'), Mockery::type('object'), false)
        ->andThrow(new InvalidArgumentException('sparse'));

    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('syncOneById')
        ->once()
        ->with($token, '12', false);

    $dataService = new stdClass;
    $activity = (object) ['Id' => '12'];

    TimeActivityWriteResponse::persistSnapshot($snapshots, $sync, $token, $dataService, $activity);
});

it('throws when a write response is missing a quickbooks id during fallback', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $snapshots = Mockery::mock(TimeActivitySnapshotService::class);
    $snapshots->shouldReceive('upsertFromQboEntity')->once()->andThrow(new InvalidArgumentException('sparse'));

    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldNotReceive('syncOneById');

    expect(fn () => TimeActivityWriteResponse::persistSnapshot(
        $snapshots,
        $sync,
        $token,
        new stdClass,
        (object) [],
    ))->toThrow(InvalidArgumentException::class, 'QuickBooks write response is missing a time activity id.');
});
