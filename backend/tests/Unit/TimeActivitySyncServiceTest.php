<?php

use App\Enums\TimeActivityReconcileScope;
use App\Exceptions\QuickBooksException;
use App\Models\QboRealmSyncState;
use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\User;
use App\Services\OrganizationTimezoneService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use App\Services\TimeActivityDisplayEnricherService;
use App\Services\TimeActivitySnapshotService;
use App\Services\TimeActivitySyncService;
use App\Support\QboCustomerResolver;
use App\Support\TimeActivityReferenceNameLookup;
use App\Support\TimeActivitySnapshotMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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
    $mapper = new TimeActivitySnapshotMapper(
        new QboCustomerResolver(new QuickBooksApiErrorFormatterService),
        app(OrganizationTimezoneService::class),
    );
    $snapshots = new TimeActivitySnapshotService($mapper, $enricher);

    return new TimeActivitySyncService(
        $quickBooks,
        new QuickBooksApiErrorFormatterService,
        $snapshots,
        app(OrganizationTimezoneService::class),
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

it('filters lookback steps and scans each configured window', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [7, 14, 120, 0, -3],
        'quickbooks.time_activities_lookback_days' => 30,
        'quickbooks.time_activities_max_results' => 2,
        'quickbooks.time_activities_query_batch_size' => 2,
        'quickbooks.time_activities_scan_max_pages' => 2,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->times(2)
        ->andReturn([], []);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    expect(makeTimeActivitySyncService($dataService)->reconcileRealm($token))->toBe(0);
});

it('paginates quickbooks windows and purges stale snapshots after a complete scan', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [30],
        'quickbooks.time_activities_lookback_days' => 30,
        'quickbooks.time_activities_max_results' => 1,
        'quickbooks.time_activities_query_batch_size' => 1,
        'quickbooks.time_activities_scan_max_pages' => 3,
    ]);

    $activity = (object) [
        'Id' => 'fresh',
        'EmployeeRef' => (object) ['value' => '7'],
        'StartTime' => '2026-07-29T09:00:00',
        'TxnDate' => '2026-07-29',
    ];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->twice()
        ->andReturn([$activity], []);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();
    TimeActivitySnapshot::factory()
        ->forRealm($token->realm_id)
        ->forEmployee('7')
        ->create(['qbo_id' => 'stale', 'txn_date' => '2026-07-29']);

    $upserted = makeTimeActivitySyncService($dataService)->reconcileRealm($token);

    expect($upserted)->toBe(1)
        ->and(TimeActivitySnapshot::query()->where('qbo_id', 'stale')->exists())->toBeFalse()
        ->and(TimeActivitySnapshot::query()->where('qbo_id', 'fresh')->exists())->toBeTrue();
});

it('skips invalid activity rows and incomplete multi-page scans avoid purge', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [30],
        'quickbooks.time_activities_lookback_days' => 30,
        'quickbooks.time_activities_max_results' => 1,
        'quickbooks.time_activities_query_batch_size' => 1,
        'quickbooks.time_activities_scan_max_pages' => 1,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            'not-an-object',
            (object) [
                'Id' => '1',
                'EmployeeRef' => (object) ['value' => '7'],
                'StartTime' => '2026-07-29T09:00:00',
                'TxnDate' => '2026-07-29',
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();
    TimeActivitySnapshot::factory()
        ->forRealm($token->realm_id)
        ->forEmployee('7')
        ->create(['qbo_id' => 'stale', 'txn_date' => '2026-07-29']);

    expect(makeTimeActivitySyncService($dataService)->reconcileRealm($token))->toBe(1)
        ->and(TimeActivitySnapshot::query()->where('qbo_id', 'stale')->exists())->toBeTrue();
});

it('logs invalid quickbooks rows encountered during reconcile import', function () {
    Log::spy();

    config([
        'quickbooks.time_activities_lookback_steps' => [30],
        'quickbooks.time_activities_lookback_days' => 30,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) ['Id' => 'invalid'],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    expect(makeTimeActivitySyncService($dataService)->reconcileRealm($token))->toBe(0);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('QuickBooks reconcile skipped invalid time activity row', Mockery::on(function (array $context) use ($token): bool {
            return ($context['realm_id'] ?? '') === $token->realm_id
                && str_contains((string) ($context['message'] ?? ''), 'EmployeeRef');
        }));
});

it('throws when reconcile queries return quickbooks errors', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [30],
        'quickbooks.time_activities_lookback_days' => 30,
    ]);

    $error = Mockery::mock();
    $error->shouldReceive('getResponseBody')->andReturn('query failed');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn($error);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    makeTimeActivitySyncService($dataService)->reconcileRealm($token);
})->throws(QuickBooksException::class);

it('uses max lookback when configured steps are filtered out', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [120, 200],
        'quickbooks.time_activities_lookback_days' => 30,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    expect(makeTimeActivitySyncService($dataService)->reconcileRealm($token))->toBe(0);
});

it('syncs one activity without resolving missing names when requested', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('TimeActivity', '42')->andReturn((object) [
        'Id' => '42',
        'EmployeeRef' => (object) ['value' => '7'],
        'StartTime' => '2026-07-29T09:00:00',
        'TxnDate' => '2026-07-29',
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    makeTimeActivitySyncService($dataService)->syncOneById($token, '42', resolveMissingNames: false);

    expect(TimeActivitySnapshot::query()->where('qbo_id', '42')->exists())->toBeTrue();
});

it('uses default lookback steps when configured steps are not an array', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => 'invalid',
        'quickbooks.time_activities_lookback_days' => 90,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->times(3)->andReturn([], [], []);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    expect(makeTimeActivitySyncService($dataService)->reconcileRealm($token))->toBe(0);
});

it('imports only the smallest lookback window in recent reconcile scope', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [14, 30, 90],
        'quickbooks.time_activities_lookback_days' => 90,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    makeTimeActivitySyncService($dataService)->reconcileRealm($token, TimeActivityReconcileScope::Recent);
});

it('skips scheduled reconcile for realms synced recently via webhook', function () {
    config([
        'quickbooks.time_activities_reconcile_skip_hours' => 2,
        'quickbooks.time_activities_lookback_steps' => [30],
        'quickbooks.time_activities_lookback_days' => 30,
    ]);

    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-recent']);
    QboRealmSyncState::query()->create([
        'realm_id' => 'realm-recent',
        'last_webhook_at' => now()->subHour(),
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldNotReceive('Query');

    expect(makeTimeActivitySyncService($dataService)->reconcileAllRealms(scheduled: true))->toBe(0);
});

it('skips scheduled reconcile for realms reconciled recently', function () {
    config([
        'quickbooks.time_activities_reconcile_skip_hours' => 2,
        'quickbooks.time_activities_lookback_steps' => [30],
        'quickbooks.time_activities_lookback_days' => 30,
    ]);

    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-reconciled']);
    QboRealmSyncState::query()->create([
        'realm_id' => 'realm-reconciled',
        'last_reconciled_at' => now()->subMinutes(30),
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldNotReceive('Query');

    expect(makeTimeActivitySyncService($dataService)->reconcileAllRealms(scheduled: true))->toBe(0);
});

it('returns zero upserts when reconcile queries return an empty first page', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [30],
        'quickbooks.time_activities_lookback_days' => 30,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    expect(makeTimeActivitySyncService($dataService)->reconcileRealm($token))->toBe(0);
});

it('does not skip realms during manual reconcile even when recently synced', function () {
    config([
        'quickbooks.time_activities_reconcile_skip_hours' => 2,
        'quickbooks.time_activities_lookback_steps' => [30],
        'quickbooks.time_activities_lookback_days' => 30,
    ]);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-recent']);
    QboRealmSyncState::query()->create([
        'realm_id' => 'realm-recent',
        'last_webhook_at' => now()->subMinute(),
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect(makeTimeActivitySyncService($dataService)->reconcileAllRealms(scheduled: false))->toBe(0);
});

it('uses recent scope during scheduled reconcile for eligible realms', function () {
    config([
        'quickbooks.time_activities_reconcile_skip_hours' => 0,
        'quickbooks.time_activities_lookback_steps' => [14, 30, 90],
        'quickbooks.time_activities_lookback_days' => 90,
    ]);

    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect(makeTimeActivitySyncService($dataService)->reconcileAllRealms(scheduled: true))->toBe(0);
});

it('stops importing when quickbooks returns a non-array query result', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [30],
        'quickbooks.time_activities_lookback_days' => 30,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    expect(makeTimeActivitySyncService($dataService)->reconcileRealm($token))->toBe(0);
});
