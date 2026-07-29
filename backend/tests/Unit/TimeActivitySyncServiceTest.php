<?php

use App\Exceptions\QuickBooksException;
use App\Models\QboRealmSyncState;
use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\User;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use App\Services\TimeActivityDisplayEnricherService;
use App\Services\TimeActivitySnapshotService;
use App\Services\TimeActivitySyncService;
use App\Support\QboCustomerResolver;
use App\Support\TimeActivityReferenceNameLookup;
use App\Support\TimeActivitySnapshotMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use QuickBooksOnline\API\DataService\DataService;

covers(TimeActivitySyncService::class);

uses(RefreshDatabase::class);

function makeTimeActivitySyncService(?DataService $dataService = null): TimeActivitySyncService
{
    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();

    if ($dataService !== null) {
        $quickBooks->shouldReceive('dataService')->andReturn($dataService);
    }

    $enricher = new TimeActivityDisplayEnricherService(
        new QboCustomerResolver(new QuickBooksApiErrorFormatterService),
        new TimeActivityReferenceNameLookup(new QuickBooksApiErrorFormatterService),
    );
    $mapper = new TimeActivitySnapshotMapper(new QboCustomerResolver(new QuickBooksApiErrorFormatterService));
    $snapshots = new TimeActivitySnapshotService($mapper, $enricher);

    return new TimeActivitySyncService(
        $quickBooks,
        new QuickBooksApiErrorFormatterService,
        $snapshots,
    );
}

it('throws QuickBooksException when FindById returns an error', function () {
    $error = Mockery::mock();
    $error->shouldReceive('getResponseBody')->andReturn('not found');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('TimeActivity', '42')->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn($error);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    makeTimeActivitySyncService($dataService)->syncOneById($token, '42');
})->throws(QuickBooksException::class);

it('soft-deletes snapshots when quickbooks returns no activity', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('TimeActivity', '42')->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();
    $snapshot = TimeActivitySnapshot::factory()
        ->forRealm($token->realm_id)
        ->forEmployee('7')
        ->create(['qbo_id' => '42']);

    makeTimeActivitySyncService($dataService)->syncOneById($token, '42');

    expect(TimeActivitySnapshot::query()->find($snapshot->id))->toBeNull();
});

it('upserts snapshots when quickbooks returns an activity', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('TimeActivity', '42')->andReturn((object) [
        'Id' => '42',
        'EmployeeRef' => (object) ['value' => '7'],
        'StartTime' => '2026-07-29T09:00:00',
        'EndTime' => '2026-07-29T10:00:00',
        'TxnDate' => '2026-07-29',
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    makeTimeActivitySyncService($dataService)->syncOneById($token, '42');

    expect(TimeActivitySnapshot::query()->where('qbo_id', '42')->exists())->toBeTrue();
});

it('reconciles a realm and updates sync state', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [365],
        'quickbooks.time_activities_lookback_days' => 365,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::on(fn (string $query) => str_contains($query, 'FROM TimeActivity')))
        ->andReturn([(object) [
            'Id' => '1',
            'EmployeeRef' => (object) ['value' => '7'],
            'StartTime' => '2026-07-29T09:00:00',
            'TxnDate' => '2026-07-29',
        ]]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $upserted = makeTimeActivitySyncService($dataService)->reconcileRealm($token);

    expect($upserted)->toBe(1)
        ->and(TimeActivitySnapshot::query()->where('qbo_id', '1')->exists())->toBeTrue()
        ->and(QboRealmSyncState::query()->where('realm_id', $token->realm_id)->exists())->toBeTrue();
});

it('reconciles every connected realm', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [365],
        'quickbooks.time_activities_lookback_days' => 365,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->twice()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    QuickBooksToken::factory()->forUser($firstUser)->create(['realm_id' => 'realm-a']);
    QuickBooksToken::factory()->forUser($secondUser)->create(['realm_id' => 'realm-b']);

    $total = makeTimeActivitySyncService($dataService)->reconcileAllRealms();

    expect($total)->toBe(0)
        ->and(QboRealmSyncState::query()->count())->toBe(2);
});
