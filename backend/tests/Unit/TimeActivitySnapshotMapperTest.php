<?php

use App\Models\TimeActivitySnapshot;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Support\QboCustomerResolver;
use App\Support\TimeActivitySnapshotMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

covers(TimeActivitySnapshotMapper::class);

uses(RefreshDatabase::class);

function makeTimeActivitySnapshotMapper(): TimeActivitySnapshotMapper
{
    return new TimeActivitySnapshotMapper(new QboCustomerResolver(new QuickBooksApiErrorFormatterService));
}

it('maps quickbooks entities into snapshot attributes', function () {
    Carbon::setTestNow('2026-07-29 12:00:00');

    $attributes = makeTimeActivitySnapshotMapper()->toAttributes('realm-1', (object) [
        'Id' => '12',
        'EmployeeRef' => (object) ['value' => '7', 'name' => 'Jane Doe'],
        'CustomerRef' => (object) ['value' => '1', 'name' => 'Acme Corp'],
        'ProjectRef' => (object) ['value' => '2', 'name' => 'Website'],
        'ItemRef' => (object) ['value' => '3', 'name' => 'Programming'],
        'StartTime' => '2026-07-29T09:00:00',
        'EndTime' => '2026-07-29T11:00:00',
        'TxnDate' => '2026-07-29',
        'Description' => 'Support work',
        'BillableStatus' => 'Billable',
        'SyncToken' => '4',
        'MetaData' => (object) ['LastUpdatedTime' => '2026-07-29T10:00:00'],
    ]);

    expect($attributes['last_synced_at']->toDateTimeString())->toBe('2026-07-29 12:00:00');
    unset($attributes['last_synced_at']);

    expect($attributes)->toBe([
        'realm_id' => 'realm-1',
        'qbo_id' => '12',
        'qbo_employee_ref' => '7',
        'customer_ref' => '1',
        'customer_name' => 'Acme Corp',
        'project_ref' => '2',
        'project_name' => 'Website',
        'item_ref' => '3',
        'item_name' => 'Programming',
        'start_time' => '2026-07-29 09:00:00',
        'end_time' => '2026-07-29 11:00:00',
        'txn_date' => '2026-07-29',
        'description' => 'Support work',
        'billable_status' => 'Billable',
        'qbo_sync_token' => '4',
        'qbo_last_updated_at' => '2026-07-29 10:00:00',
    ]);

    Carbon::setTestNow();
});

it('rejects activities without employee or id references', function () {
    $mapper = makeTimeActivitySnapshotMapper();

    expect(fn () => $mapper->toAttributes('realm-1', (object) ['Id' => '1']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $mapper->toAttributes('realm-1', (object) ['EmployeeRef' => (object) ['value' => '7']]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $mapper->toAttributes('realm-1', (object) [
            'Id' => '',
            'EmployeeRef' => (object) ['value' => '7'],
        ]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $mapper->toAttributes('realm-1', (object) [
            'Id' => '1',
            'EmployeeRef' => (object) ['value' => ''],
        ]))->toThrow(InvalidArgumentException::class);
});

it('merges enriched reference names onto attributes', function () {
    $attributes = makeTimeActivitySnapshotMapper()->mergeEnrichedNames(
        ['customer_name' => 'Old'],
        (object) [
            'CustomerRef' => (object) ['name' => 'Acme Corp'],
            'ProjectRef' => (object) ['name' => 'Website'],
            'ItemRef' => (object) ['name' => 'Programming'],
        ],
    );

    expect($attributes)->toMatchArray([
        'customer_name' => 'Acme Corp',
        'project_name' => 'Website',
        'item_name' => 'Programming',
    ]);
});

it('builds api objects with duration and reference names', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'qbo_id' => '12',
        'qbo_employee_ref' => '7',
        'customer_ref' => '1',
        'customer_name' => 'Acme Corp',
        'project_ref' => '2',
        'project_name' => 'Website',
        'item_ref' => '3',
        'item_name' => 'Programming',
        'start_time' => Carbon::parse('2026-07-29 09:00:00'),
        'end_time' => Carbon::parse('2026-07-29 11:30:00'),
        'txn_date' => '2026-07-29',
        'description' => 'Support work',
        'billable_status' => 'Billable',
    ]);

    $payload = makeTimeActivitySnapshotMapper()->toApiObject($snapshot);

    expect($payload->Id)->toBe('12')
        ->and($payload->CustomerRef)->toEqual((object) ['value' => '1', 'name' => 'Acme Corp'])
        ->and($payload->ProjectRef)->toEqual((object) ['value' => '2', 'name' => 'Website'])
        ->and($payload->ItemRef)->toEqual((object) ['value' => '3', 'name' => 'Programming'])
        ->and($payload->Hours)->toBe(2)
        ->and($payload->Minutes)->toBe(30);
});

it('omits duration when start or end time is missing', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'start_time' => null,
        'end_time' => Carbon::parse('2026-07-29 11:30:00'),
    ]);

    $payload = makeTimeActivitySnapshotMapper()->toApiObject($snapshot);

    expect(isset($payload->Hours))->toBeFalse()
        ->and(isset($payload->Minutes))->toBeFalse();
});

it('accepts array shaped quickbooks rows', function () {
    $attributes = makeTimeActivitySnapshotMapper()->toAttributes('realm-1', [
        'Id' => '9',
        'EmployeeRef' => (object) ['value' => '7'],
        'CustomerRef' => ['value' => '1', 'name' => 'Acme Corp'],
        'StartTime' => '2026-07-29T09:00:00',
        'TxnDate' => '2026-07-29',
    ]);

    expect($attributes['qbo_id'])->toBe('9')
        ->and($attributes['customer_name'])->toBe('Acme Corp');
});

it('returns nulls for invalid dates and empty reference names', function () {
    $mapper = makeTimeActivitySnapshotMapper();

    $attributes = $mapper->toAttributes('realm-1', (object) [
        'Id' => '3',
        'EmployeeRef' => (object) ['value' => '7'],
        'CustomerRef' => (object) ['value' => '1', 'name' => '   '],
        'StartTime' => 'not-a-date',
        'TxnDate' => 'also-bad',
    ]);

    expect($attributes['customer_name'])->toBeNull()
        ->and($attributes['start_time'])->toBeNull()
        ->and($attributes['txn_date'])->toBeNull();
});

it('builds api objects without optional refs', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'item_ref' => null,
        'item_name' => null,
    ]);

    $payload = makeTimeActivitySnapshotMapper()->toApiObject($snapshot);

    expect($payload->CustomerRef)->toBeNull()
        ->and($payload->ProjectRef)->toBeNull()
        ->and($payload->ItemRef)->toBeNull();
});

it('maps metadata, scalar fields, and array reference names', function () {
    $attributes = makeTimeActivitySnapshotMapper()->toAttributes('realm-1', (object) [
        'Id' => '4',
        'EmployeeRef' => (object) ['value' => '7'],
        'CustomerRef' => (object) ['value' => '1', 'name' => '  Acme  '],
        'Description' => '  Support  ',
        'BillableStatus' => 'Billable',
        'SyncToken' => '2',
        'MetaData' => (object) ['LastUpdatedTime' => '2026-07-29T10:15:00'],
        'StartTime' => '2026-07-29T09:00:00',
        'TxnDate' => '2026-07-29',
    ]);

    expect($attributes['description'])->toBe('Support')
        ->and($attributes['billable_status'])->toBe('Billable')
        ->and($attributes['qbo_sync_token'])->toBe('2')
        ->and($attributes['qbo_last_updated_at'])->toBe('2026-07-29 10:15:00')
        ->and($attributes['customer_name'])->toBe('Acme');
});

it('keeps zero duration when end time is before start time', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'start_time' => Carbon::parse('2026-07-29 11:00:00'),
        'end_time' => Carbon::parse('2026-07-29 09:00:00'),
    ]);

    $payload = makeTimeActivitySnapshotMapper()->toApiObject($snapshot);

    expect($payload->Hours)->toBe(0)
        ->and($payload->Minutes)->toBe(0);
});

it('builds api refs with names and skips blank enriched names', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'customer_ref' => '1',
        'customer_name' => 'Acme Corp',
        'project_ref' => '2',
        'project_name' => '',
        'item_ref' => '3',
        'item_name' => 'Programming',
    ]);

    $payload = makeTimeActivitySnapshotMapper()->toApiObject($snapshot);

    expect($payload->CustomerRef)->toEqual((object) ['value' => '1', 'name' => 'Acme Corp'])
        ->and($payload->ProjectRef)->toEqual((object) ['value' => '2'])
        ->and($payload->ItemRef)->toEqual((object) ['value' => '3', 'name' => 'Programming']);

    $merged = makeTimeActivitySnapshotMapper()->mergeEnrichedNames(
        ['customer_name' => 'Old'],
        (object) ['CustomerRef' => (object) ['name' => '   ']],
    );

    expect($merged['customer_name'])->toBe('Old');
});

it('maps object shaped rows with precise duration and api timestamps', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'qbo_id' => '12',
        'qbo_employee_ref' => '7',
        'start_time' => Carbon::parse('2026-07-29 09:00:00'),
        'end_time' => Carbon::parse('2026-07-29 10:05:00'),
        'txn_date' => '2026-07-29',
        'description' => 'Support work',
        'billable_status' => 'Billable',
    ]);

    $payload = makeTimeActivitySnapshotMapper()->toApiObject($snapshot);

    expect($payload->EmployeeRef)->toEqual((object) ['value' => '7'])
        ->and($payload->StartTime)->toBe('2026-07-29T09:00:00+00:00')
        ->and($payload->EndTime)->toBe('2026-07-29T10:05:00+00:00')
        ->and($payload->TxnDate)->toBe('2026-07-29')
        ->and($payload->Description)->toBe('Support work')
        ->and($payload->BillableStatus)->toBe('Billable')
        ->and($payload->Hours)->toBe(1)
        ->and($payload->Minutes)->toBe(5);
});

it('normalizes blank scalar fields and invalid metadata timestamps', function () {
    $mapper = makeTimeActivitySnapshotMapper();

    $attributes = $mapper->toAttributes('realm-1', (object) [
        'Id' => '5',
        'EmployeeRef' => (object) ['value' => '7'],
        'Description' => '   ',
        'MetaData' => (object) ['LastUpdatedTime' => 'not-a-date'],
        'CustomerRef' => (object) ['value' => '1', 'name' => '  '],
        'ProjectRef' => ['name' => '  Website  '],
    ]);

    expect($attributes['description'])->toBeNull()
        ->and($attributes['qbo_last_updated_at'])->toBeNull()
        ->and($attributes['customer_name'])->toBeNull()
        ->and($mapper->mergeEnrichedNames($attributes, (object) [
            'ProjectRef' => ['name' => '  Website  '],
        ])['project_name'])->toBe('Website');
});

it('rejects missing employee references before id validation', function () {
    $mapper = makeTimeActivitySnapshotMapper();

    expect(fn () => $mapper->toAttributes('realm-1', (object) [
        'Id' => '1',
        'EmployeeRef' => null,
    ]))->toThrow(InvalidArgumentException::class, 'EmployeeRef');
});

it('omits optional datetime fields when quickbooks rows omit them', function () {
    $attributes = makeTimeActivitySnapshotMapper()->toAttributes('realm-1', (object) [
        'Id' => '8',
        'EmployeeRef' => (object) ['value' => '7'],
    ]);

    expect($attributes['start_time'])->toBeNull()
        ->and($attributes['end_time'])->toBeNull()
        ->and($attributes['txn_date'])->toBeNull();
});

it('reads array shaped reference names and ignores array metadata', function () {
    $mapper = makeTimeActivitySnapshotMapper();

    $attributes = $mapper->toAttributes('realm-1', [
        'Id' => '9',
        'EmployeeRef' => (object) ['value' => '7'],
        'ItemRef' => ['value' => '3', 'name' => 'Programming'],
        'MetaData' => ['LastUpdatedTime' => '2026-07-29T10:00:00'],
    ]);

    expect($attributes['item_name'])->toBe('Programming')
        ->and($attributes['qbo_last_updated_at'])->toBeNull();
});

it('returns null api refs for blank quickbooks identifiers', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'customer_ref' => '',
        'project_ref' => null,
    ]);

    $payload = makeTimeActivitySnapshotMapper()->toApiObject($snapshot);

    expect($payload->CustomerRef)->toBeNull()
        ->and($payload->ProjectRef)->toBeNull();
});

it('serializes nullable txn dates in api objects', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'txn_date' => null,
        'start_time' => null,
        'end_time' => null,
    ]);

    $payload = makeTimeActivitySnapshotMapper()->toApiObject($snapshot);

    expect($payload->TxnDate)->toBeNull()
        ->and(isset($payload->Hours))->toBeFalse();
});

it('ignores empty metadata timestamps and blank array reference names', function () {
    $mapper = makeTimeActivitySnapshotMapper();

    $attributes = $mapper->toAttributes('realm-1', [
        'Id' => '10',
        'EmployeeRef' => (object) ['value' => '7'],
        'MetaData' => (object) ['LastUpdatedTime' => '   '],
        'CustomerRef' => ['value' => '1', 'name' => ''],
    ]);

    expect($attributes['qbo_last_updated_at'])->toBeNull()
        ->and($attributes['customer_name'])->toBeNull();
});

it('computes sub-hour durations without rounding up to a full hour', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'start_time' => Carbon::parse('2026-07-29 09:00:00'),
        'end_time' => Carbon::parse('2026-07-29 09:59:59'),
    ]);

    $payload = makeTimeActivitySnapshotMapper()->toApiObject($snapshot);

    expect($payload->Hours)->toBe(0)
        ->and($payload->Minutes)->toBe(59);
});

it('reads object metadata timestamps from quickbooks rows', function () {
    $attributes = makeTimeActivitySnapshotMapper()->toAttributes('realm-1', (object) [
        'Id' => '11',
        'EmployeeRef' => (object) ['value' => '7'],
        'MetaData' => (object) ['LastUpdatedTime' => '2026-07-29T10:15:00'],
    ]);

    expect($attributes['qbo_last_updated_at'])->toBe('2026-07-29 10:15:00');
});
