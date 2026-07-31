<?php

use App\Jobs\ReconcileRealmTimeActivitiesJob;
use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\User;
use App\Services\OrganizationTimezoneService;
use App\Services\QboEmployeeAuthorizationService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use App\Services\TimeActivityDisplayEnricherService;
use App\Services\TimeActivityListService;
use App\Services\TimeActivityReconcileCoordinatorService;
use App\Services\TimeActivitySnapshotService;
use App\Services\TimeActivitySyncService;
use App\Support\QboCustomerResolver;
use App\Support\TimeActivityReferenceNameLookup;
use App\Support\TimeActivitySnapshotMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use QuickBooksOnline\API\DataService\DataService;

covers(TimeActivityListService::class);
covers(TimeActivitySyncService::class);
covers(TimeActivitySnapshotService::class);
covers(TimeActivitySnapshotMapper::class);

uses(RefreshDatabase::class);

const LIST_MIN_TXN_DATE = '2025-07-29';
const LIST_PAGE_QUERY = "SELECT * FROM TimeActivity WHERE TxnDate >= '".LIST_MIN_TXN_DATE."' STARTPOSITION 1 MAXRESULTS 100";

beforeEach(function () {
    Carbon::setTestNow('2026-07-29 12:00:00');
    config([
        'quickbooks.time_activities_lookback_steps' => [365],
        'quickbooks.time_activities_lookback_days' => 365,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeTimeActivityListService(?DataService $dataService = null): TimeActivityListService
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
    $sync = new TimeActivitySyncService(
        $quickBooks,
        new QuickBooksApiErrorFormatterService,
        $snapshots,
        app(OrganizationTimezoneService::class),
    );
    $coordinator = new TimeActivityReconcileCoordinatorService($snapshots, $sync);

    return new TimeActivityListService(
        $quickBooks,
        new QboEmployeeAuthorizationService,
        new QuickBooksApiErrorFormatterService,
        $snapshots,
        $coordinator,
    );
}

it('lists time activities for a user from snapshots', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = quickBooksTokenWithListedActivities($user, '7', 1);

    $result = makeTimeActivityListService()->listForUser($user, $token);

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]->Id)->toBe('1')
        ->and($result['meta']['truncated'])->toBeFalse();
});

it('filters out activities that belong to other employees', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();
    TimeActivitySnapshot::factory()->forRealm($token->realm_id)->forEmployee('7')->create(['qbo_id' => '1']);
    TimeActivitySnapshot::factory()->forRealm($token->realm_id)->forEmployee('99')->create(['qbo_id' => '2']);

    $result = makeTimeActivityListService()->listForUser($user, $token);

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]->Id)->toBe('1');
});

it('clamps invalid pagination arguments before reading snapshots', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = quickBooksTokenWithListedActivities($user, '7', 1);

    $result = makeTimeActivityListService()->listForUser($user, $token, 0, 0);

    expect($result['meta']['start_position'])->toBe(1)
        ->and($result['meta']['max_results'])->toBe(1);
});

it('marks list responses as truncated when more snapshot rows exist', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = quickBooksTokenWithListedActivities($user, '7', 101);

    $result = makeTimeActivityListService()->listForUser($user, $token, 1, 100);

    expect($result['meta']['count'])->toBe(100)
        ->and($result['meta']['truncated'])->toBeTrue();
});

it('does not mark list responses as truncated when all rows fit on one page', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = quickBooksTokenWithListedActivities($user, '7', 50);

    $result = makeTimeActivityListService()->listForUser($user, $token, 1, 100);

    expect($result['meta']['count'])->toBe(50)
        ->and($result['meta']['truncated'])->toBeFalse();
});

it('purges stale snapshots when reconciling from quickbooks', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(LIST_PAGE_QUERY)
        ->andReturn([(object) [
            'Id' => '1',
            'EmployeeRef' => (object) ['value' => '7'],
            'StartTime' => '2026-07-29T09:00:00',
            'TxnDate' => '2026-07-29',
        ]]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();
    TimeActivitySnapshot::factory()->forRealm($token->realm_id)->forEmployee('7')->create([
        'qbo_id' => 'ghost',
        'txn_date' => '2026-07-29',
    ]);

    $result = makeTimeActivityListService($dataService)->listForUser($user, $token, 1, 100, true);

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]->Id)->toBe('1')
        ->and(TimeActivitySnapshot::query()->where('qbo_id', 'ghost')->exists())->toBeFalse();
});

it('does not purge snapshots when the reconcile scan is truncated', function () {
    config([
        'quickbooks.time_activities_lookback_steps' => [365],
        'quickbooks.time_activities_lookback_days' => 365,
        'quickbooks.time_activities_scan_max_pages' => 1,
        'quickbooks.time_activities_query_batch_size' => 1,
        'quickbooks.time_activities_max_results' => 1,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([(object) [
            'Id' => '1',
            'EmployeeRef' => (object) ['value' => '7'],
            'StartTime' => '2026-07-29T09:00:00',
            'TxnDate' => '2026-07-29',
        ]]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();
    TimeActivitySnapshot::factory()->forRealm($token->realm_id)->forEmployee('7')->create([
        'qbo_id' => 'ghost',
        'txn_date' => '2026-07-29',
    ]);

    makeTimeActivityListService($dataService)->listForUser($user, $token, 1, 100, true);

    expect(TimeActivitySnapshot::query()->where('qbo_id', 'ghost')->exists())->toBeTrue();
});

it('does not purge snapshots when reconcile sees no quickbooks activities', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(LIST_PAGE_QUERY)
        ->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();
    TimeActivitySnapshot::factory()->forRealm($token->realm_id)->forEmployee('7')->create([
        'qbo_id' => 'ghost',
        'txn_date' => '2026-07-29',
    ]);

    makeTimeActivityListService($dataService)->listForUser($user, $token, 1, 100, true);

    expect(TimeActivitySnapshot::query()->where('qbo_id', 'ghost')->exists())->toBeTrue();
});

it('purges snapshots with null txn_date inside the lookback window', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(LIST_PAGE_QUERY)
        ->andReturn([(object) [
            'Id' => '1',
            'EmployeeRef' => (object) ['value' => '7'],
            'StartTime' => '2026-07-29T09:00:00',
            'TxnDate' => '2026-07-29',
        ]]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();
    TimeActivitySnapshot::factory()->forRealm($token->realm_id)->forEmployee('7')->create([
        'qbo_id' => 'ghost',
        'txn_date' => null,
    ]);

    makeTimeActivityListService($dataService)->listForUser($user, $token, 1, 100, true);

    expect(TimeActivitySnapshot::query()->where('qbo_id', 'ghost')->exists())->toBeFalse();
});

it('queues a backfill when the realm has no snapshots', function () {
    Queue::fake();

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $result = makeTimeActivityListService()->listForUser($user, $token);

    Queue::assertPushed(ReconcileRealmTimeActivitiesJob::class, fn (ReconcileRealmTimeActivitiesJob $job) => $job->tokenId === $token->id
        && $job->fullLookback === true);
    expect($result['data'])->toBe([]);
});

it('returns an empty list when no snapshots exist yet and reconcile is queued', function () {
    Queue::fake();

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();

    expect(makeTimeActivityListService()->listForUser($user, $token)['data'])->toBe([]);
});

it('aborts when the qbo employee is missing', function () {
    $user = User::factory()->create(['qbo_employee_ref' => null]);
    $token = QuickBooksToken::factory()->forUser($user)->create();

    makeTimeActivityListService()->listForUser($user, $token);
})->throws(HttpResponseException::class);

it('clamps max results to the configured quickbooks maximum', function () {
    config(['quickbooks.time_activities_max_results' => 25]);

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = quickBooksTokenWithListedActivities($user, '7', 30);

    $result = makeTimeActivityListService()->listForUser($user, $token, 1, 75);

    expect($result['meta']['max_results'])->toBe(25)
        ->and($result['meta']['count'])->toBe(25);
});

it('does not mark the final full page as truncated', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = quickBooksTokenWithListedActivities($user, '7', 100);

    $result = makeTimeActivityListService()->listForUser($user, $token, 1, 100);

    expect($result['meta']['count'])->toBe(100)
        ->and($result['meta']['truncated'])->toBeFalse();
});
