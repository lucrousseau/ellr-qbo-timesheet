<?php

use App\Models\TimeActivitySnapshot;
use App\Services\OrganizationTimezoneService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\TimeActivityDisplayEnricherService;
use App\Services\TimeActivitySnapshotService;
use App\Support\QboCustomerResolver;
use App\Support\TimeActivityReferenceNameLookup;
use App\Support\TimeActivitySnapshotMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use QuickBooksOnline\API\DataService\DataService;

covers(TimeActivitySnapshotService::class);

uses(RefreshDatabase::class);

function makeTimeActivitySnapshotService(): TimeActivitySnapshotService
{
    $enricher = new TimeActivityDisplayEnricherService(
        new QboCustomerResolver(new QuickBooksApiErrorFormatterService),
        new TimeActivityReferenceNameLookup(new QuickBooksApiErrorFormatterService),
    );

    return new TimeActivitySnapshotService(
        new TimeActivitySnapshotMapper(
            new QboCustomerResolver(new QuickBooksApiErrorFormatterService),
            app(OrganizationTimezoneService::class),
        ),
        $enricher,
    );
}

it('upserts snapshots from quickbooks entities', function () {
    $dataService = Mockery::mock(DataService::class);

    $snapshot = makeTimeActivitySnapshotService()->upsertFromQboEntity(
        'realm-1',
        $dataService,
        (object) [
            'Id' => '12',
            'EmployeeRef' => (object) ['value' => '7', 'name' => 'Jane Doe'],
            'CustomerRef' => (object) ['value' => '1', 'name' => 'Acme Corp'],
            'StartTime' => '2026-07-29T09:00:00',
            'EndTime' => '2026-07-29T10:00:00',
            'TxnDate' => '2026-07-29',
            'Description' => 'Support',
        ],
    );

    expect($snapshot->qbo_id)->toBe('12')
        ->and($snapshot->customer_name)->toBe('Acme Corp')
        ->and($snapshot->deleted_at)->toBeNull();
});

it('preserves display names on lightweight upserts', function () {
    $dataService = Mockery::mock(DataService::class);
    $service = makeTimeActivitySnapshotService();

    TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create([
            'qbo_id' => '12',
            'customer_name' => 'Acme Corp',
            'project_name' => 'Website',
            'item_name' => 'Programming',
        ]);

    $snapshot = $service->upsertFromQboEntity(
        'realm-1',
        $dataService,
        (object) [
            'Id' => '12',
            'EmployeeRef' => (object) ['value' => '7'],
            'StartTime' => '2026-07-29T09:00:00',
            'EndTime' => '2026-07-29T10:00:00',
            'TxnDate' => '2026-07-29',
        ],
        resolveMissingNames: false,
    );

    expect($snapshot->customer_name)->toBe('Acme Corp')
        ->and($snapshot->project_name)->toBe('Website')
        ->and($snapshot->item_name)->toBe('Programming');
});

it('lists and finds api objects for an employee', function () {
    $service = makeTimeActivitySnapshotService();

    TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => '1', 'customer_name' => 'Acme Corp']);
    TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('99')
        ->create(['qbo_id' => '2']);

    [$objects, $total] = $service->listApiObjectsForEmployee('realm-1', '7', 1, 10);

    expect($total)->toBe(1)
        ->and($objects[0]->Id)->toBe('1')
        ->and($service->findApiObject('realm-1', '1')?->Id)->toBe('1')
        ->and($service->findApiObject('realm-1', 'missing'))->toBeNull();
});

it('reports whether a realm has snapshots and purges stale rows safely', function () {
    $service = makeTimeActivitySnapshotService();

    expect($service->realmHasSnapshots('realm-1'))->toBeFalse()
        ->and($service->purgeStaleInLookback('realm-1', '2026-07-01', []))->toBe(0);

    TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'stale', 'txn_date' => '2026-07-29']);
    TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'fresh', 'txn_date' => '2026-07-29']);

    expect($service->realmHasSnapshots('realm-1'))->toBeTrue();

    $deleted = $service->purgeStaleInLookback('realm-1', '2026-07-01', ['fresh']);

    expect($deleted)->toBe(1)
        ->and(TimeActivitySnapshot::query()->where('qbo_id', 'stale')->exists())->toBeFalse()
        ->and(TimeActivitySnapshot::query()->where('qbo_id', 'fresh')->exists())->toBeTrue();
});

it('soft-deletes snapshots by quickbooks id', function () {
    $snapshot = TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => '55']);

    makeTimeActivitySnapshotService()->softDeleteByQboId('realm-1', '55');

    expect(TimeActivitySnapshot::query()->find($snapshot->id))->toBeNull();
});

it('upserts array shaped activities and restores soft-deleted rows', function () {
    $dataService = Mockery::mock(DataService::class);
    $service = makeTimeActivitySnapshotService();

    $deleted = TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => '88']);
    $deleted->delete();

    $snapshot = $service->upsertFromQboEntity(
        'realm-1',
        $dataService,
        [
            'Id' => '88',
            'EmployeeRef' => (object) ['value' => '7'],
            'StartTime' => '2026-07-29T09:00:00',
            'TxnDate' => '2026-07-29',
        ],
    );

    expect($snapshot->refresh()->deleted_at)->toBeNull()
        ->and($snapshot->qbo_id)->toBe('88');
});

it('paginates employee snapshots and purges null txn dates', function () {
    $service = makeTimeActivitySnapshotService();

    TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'older', 'txn_date' => '2026-07-01', 'start_time' => '2026-07-01 08:00:00']);
    TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'newer', 'txn_date' => '2026-07-29', 'start_time' => '2026-07-29 09:00:00']);
    TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'null-date', 'txn_date' => null, 'start_time' => '2026-07-15 09:00:00']);

    [$page, $total] = $service->listApiObjectsForEmployee('realm-1', '7', 2, 1);

    expect($total)->toBe(3)
        ->and($page)->toHaveCount(1)
        ->and($page[0]->Id)->toBe('null-date');

    $deleted = $service->purgeStaleInLookback('realm-1', '2026-07-01', ['newer']);

    expect($deleted)->toBe(2)
        ->and(TimeActivitySnapshot::query()->where('qbo_id', 'null-date')->exists())->toBeFalse()
        ->and(TimeActivitySnapshot::query()->where('qbo_id', 'older')->exists())->toBeFalse();
});

it('preserves existing names when lightweight upserts send empty strings', function () {
    $dataService = Mockery::mock(DataService::class);
    $service = makeTimeActivitySnapshotService();

    TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create([
            'qbo_id' => '12',
            'customer_name' => 'Acme Corp',
            'project_name' => 'Website',
            'item_name' => 'Programming',
        ]);

    $snapshot = $service->upsertFromQboEntity(
        'realm-1',
        $dataService,
        (object) [
            'Id' => '12',
            'EmployeeRef' => (object) ['value' => '7'],
            'CustomerRef' => (object) ['value' => '1', 'name' => ''],
            'StartTime' => '2026-07-29T09:00:00',
            'TxnDate' => '2026-07-29',
        ],
        resolveMissingNames: false,
    );

    expect($snapshot->customer_name)->toBe('Acme Corp')
        ->and($snapshot->project_name)->toBe('Website')
        ->and($snapshot->item_name)->toBe('Programming');
});

it('enriches snapshots when resolveMissingNames is enabled', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Customer WHERE Id IN \\('58'\\)/"))
        ->andReturn([(object) ['Id' => '58', 'DisplayName' => 'Acme Corp']]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $snapshot = makeTimeActivitySnapshotService()->upsertFromQboEntity(
        'realm-1',
        $dataService,
        (object) [
            'Id' => '12',
            'EmployeeRef' => (object) ['value' => '7'],
            'CustomerRef' => '58',
            'StartTime' => '2026-07-29T09:00:00',
            'TxnDate' => '2026-07-29',
        ],
        resolveMissingNames: true,
    );

    expect($snapshot->customer_name)->toBe('Acme Corp');
});

it('returns an empty batch result without writing snapshots', function () {
    $dataService = Mockery::mock(DataService::class);

    expect(makeTimeActivitySnapshotService()->upsertBatchFromQboEntities('realm-1', $dataService, []))
        ->toBe([]);
});

it('upserts multiple quickbooks entities in one batch without extra name lookups', function () {
    $dataService = Mockery::mock(DataService::class);
    $service = makeTimeActivitySnapshotService();

    $snapshots = $service->upsertBatchFromQboEntities(
        'realm-1',
        $dataService,
        [
            (object) [
                'Id' => '41',
                'EmployeeRef' => (object) ['value' => '7'],
                'StartTime' => '2026-07-29T09:00:00',
                'TxnDate' => '2026-07-29',
            ],
            (object) [
                'Id' => '42',
                'EmployeeRef' => (object) ['value' => '7'],
                'StartTime' => '2026-07-29T10:00:00',
                'TxnDate' => '2026-07-29',
            ],
        ],
        resolveMissingNames: false,
    );

    expect($snapshots)->toHaveCount(2)
        ->and($snapshots[0]->qbo_id)->toBe('41')
        ->and($snapshots[1]->qbo_id)->toBe('42');
});

it('enriches quickbooks entities when batch upserts request missing names', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $snapshots = makeTimeActivitySnapshotService()->upsertBatchFromQboEntities(
        'realm-1',
        $dataService,
        [
            (object) [
                'Id' => '51',
                'EmployeeRef' => (object) ['value' => '7'],
                'CustomerRef' => '58',
                'StartTime' => '2026-07-29T09:00:00',
                'TxnDate' => '2026-07-29',
            ],
        ],
        resolveMissingNames: true,
    );

    expect($snapshots)->toHaveCount(1)
        ->and($snapshots[0]->qbo_id)->toBe('51');
});

it('starts employee listing from the first page', function () {
    $service = makeTimeActivitySnapshotService();

    foreach ([
        'first' => '2026-07-29 09:00:00',
        'second' => '2026-07-28 09:00:00',
        'third' => '2026-07-27 09:00:00',
    ] as $qboId => $startTime) {
        TimeActivitySnapshot::factory()
            ->forRealm('realm-1')
            ->forEmployee('7')
            ->create([
                'qbo_id' => $qboId,
                'start_time' => $startTime,
            ]);
    }

    [$firstPage] = $service->listApiObjectsForEmployee('realm-1', '7', 1, 1);
    [$secondPage] = $service->listApiObjectsForEmployee('realm-1', '7', 2, 1);

    expect($firstPage[0]->Id)->toBe('first')
        ->and($secondPage[0]->Id)->toBe('second');
});

it('preserves existing names when lightweight upserts omit nullable fields', function () {
    $dataService = Mockery::mock(DataService::class);
    $service = makeTimeActivitySnapshotService();

    TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create([
            'qbo_id' => '12',
            'customer_name' => 'Acme Corp',
            'project_name' => null,
            'item_name' => 'Programming',
        ]);

    $snapshot = $service->upsertFromQboEntity(
        'realm-1',
        $dataService,
        (object) [
            'Id' => '12',
            'EmployeeRef' => (object) ['value' => '7'],
            'StartTime' => '2026-07-29T09:00:00',
            'TxnDate' => '2026-07-29',
        ],
        resolveMissingNames: false,
    );

    expect($snapshot->customer_name)->toBe('Acme Corp')
        ->and($snapshot->item_name)->toBe('Programming');
});

it('upserts array shaped activities through both enrichment modes', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);
    $service = makeTimeActivitySnapshotService();

    $enriched = $service->upsertFromQboEntity(
        'realm-1',
        $dataService,
        [
            'Id' => '21',
            'EmployeeRef' => (object) ['value' => '7'],
            'StartTime' => '2026-07-29T09:00:00',
            'TxnDate' => '2026-07-29',
        ],
        resolveMissingNames: true,
    );

    $lightweight = $service->upsertFromQboEntity(
        'realm-1',
        $dataService,
        [
            'Id' => '22',
            'EmployeeRef' => (object) ['value' => '7'],
            'StartTime' => '2026-07-29T09:00:00',
            'TxnDate' => '2026-07-29',
        ],
        resolveMissingNames: false,
    );

    expect($enriched->qbo_id)->toBe('21')
        ->and($lightweight->qbo_id)->toBe('22');
});

it('preserves cached customer names when lightweight upserts omit customer refs', function () {
    $dataService = Mockery::mock(DataService::class);
    $service = makeTimeActivitySnapshotService();

    TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create([
            'qbo_id' => '12',
            'customer_name' => 'Acme Corp',
            'project_name' => 'Website',
            'item_name' => 'Programming',
        ]);

    $snapshot = $service->upsertFromQboEntity(
        'realm-1',
        $dataService,
        (object) [
            'Id' => '12',
            'EmployeeRef' => (object) ['value' => '7'],
            'StartTime' => '2026-07-29T09:00:00',
            'TxnDate' => '2026-07-29',
        ],
        resolveMissingNames: false,
    );

    expect($snapshot->customer_name)->toBe('Acme Corp')
        ->and($snapshot->project_name)->toBe('Website')
        ->and($snapshot->item_name)->toBe('Programming');
});

it('does not purge snapshots outside the lookback txn date window', function () {
    $service = makeTimeActivitySnapshotService();

    TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'old', 'txn_date' => '2026-06-01']);

    expect($service->purgeStaleInLookback('realm-1', '2026-07-01', ['fresh']))->toBe(0)
        ->and(TimeActivitySnapshot::query()->where('qbo_id', 'old')->exists())->toBeTrue();
});

it('casts array shaped activities to objects before enrichment', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Customer WHERE Id IN \\('58'\\)/"))
        ->andReturn([(object) ['Id' => '58', 'DisplayName' => 'Acme Corp']]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $snapshot = makeTimeActivitySnapshotService()->upsertFromQboEntity(
        'realm-1',
        $dataService,
        [
            'Id' => '31',
            'EmployeeRef' => (object) ['value' => '7'],
            'CustomerRef' => '58',
            'StartTime' => '2026-07-29T09:00:00',
            'TxnDate' => '2026-07-29',
        ],
        resolveMissingNames: true,
    );

    expect($snapshot->customer_name)->toBe('Acme Corp');
});
