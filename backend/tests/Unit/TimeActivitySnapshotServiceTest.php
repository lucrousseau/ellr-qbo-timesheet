<?php

use App\Models\TimeActivitySnapshot;
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
        new TimeActivitySnapshotMapper(new QboCustomerResolver(new QuickBooksApiErrorFormatterService)),
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
