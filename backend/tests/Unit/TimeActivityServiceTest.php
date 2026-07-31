<?php

use App\Http\Requests\StoreTimeActivityRequest;
use App\Http\Requests\UpdateTimeActivityRequest;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboEmployeeAuthorizationService;
use App\Services\QboPickerDisplayNameService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use App\Services\TimeActivityService;
use App\Services\TimeActivitySnapshotService;
use App\Services\TimeActivitySyncService;
use App\Support\TimeActivityTimeValidation;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use QuickBooksOnline\API\Data\IPPTimeActivity;
use QuickBooksOnline\API\DataService\DataService;
use Tests\Support\MockeryCapture;

covers(TimeActivityService::class);

function makeDisplayNamesMock(): QboPickerDisplayNameService
{
    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldReceive('employeeDisplayName')->zeroOrMoreTimes()->andReturn('Jane Doe');

    return $displayNames;
}

function makeTimeActivityService(
    DataService $dataService,
    ?TimeActivitySnapshotService $snapshots = null,
): TimeActivityService {
    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('dataService')->andReturn($dataService);
    $employeeAuth = new QboEmployeeAuthorizationService;
    $apiErrors = new QuickBooksApiErrorFormatterService;
    $snapshots ??= Mockery::mock(TimeActivitySnapshotService::class);
    $snapshots->shouldReceive('upsertFromQboEntity')->byDefault();
    $snapshots->shouldReceive('softDeleteByQboId')->byDefault();
    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('syncOneById')->byDefault();
    $displayNames = makeDisplayNamesMock();

    return new TimeActivityService($quickBooks, $employeeAuth, $apiErrors, $snapshots, $sync, $displayNames);
}

function makeUserWithEmployee(): User
{
    return User::factory()->make([
        'organization_id' => 1,
        'qbo_employee_ref' => '7',
    ]);
}

it('validates store time activity rules', function () {
    $request = new StoreTimeActivityRequest;
    $validator = Validator::make([
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
        'description' => 'Support',
    ], $request->rules());

    expect($validator->passes())->toBeTrue();
    expect($request->authorize())->toBeTrue();
});

it('validates update time activity rules', function () {
    $request = new UpdateTimeActivityRequest;
    $validator = Validator::make([
        'start_time' => '2026-07-27T10:00:00',
    ], $request->rules());

    expect($validator->passes())->toBeTrue();
    expect($request->authorize())->toBeTrue();
});

it('rejects update payloads when end time is not after start time', function () {
    $request = UpdateTimeActivityRequest::create('/', 'PATCH', [
        'start_time' => '2026-07-27T17:00:00',
        'end_time' => '2026-07-27T09:00:00',
    ]);
    $request->setContainer(app());
    $validator = Validator::make($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('end_time'))->toBeTrue();
});

it('rejects partial time updates when existing end time is missing', function () {
    $existing = new IPPTimeActivity;
    $existing->Id = '10';
    $existing->SyncToken = '0';
    $existing->StartTime = '2026-07-27T09:00:00';
    $existing->EmployeeRef = (object) ['value' => '7'];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($existing);
    $dataService->shouldNotReceive('Update');
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    try {
        $service->updateForUser($user, $token, '10', [
            'start_time' => '2026-07-27T10:00:00',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => TimeActivityTimeValidation::incompleteTimeFieldsMessage(),
                'errors' => [
                    'start_time' => [TimeActivityTimeValidation::incompleteTimeFieldsMessage()],
                    'end_time' => [TimeActivityTimeValidation::incompleteTimeFieldsMessage()],
                ],
            ]);
    }
});

it('creates a time activity for a user', function () {
    $captured = null;
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->with(Mockery::capture($captured))->andReturn((object) ['Id' => '99']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    $result = $service->createForUser($user, $token, [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
        'description' => 'Client work',
        'customer_ref' => '42',
        'customer_name' => 'Acme',
    ]);

    $payload = MockeryCapture::unwrap($captured);

    expect($result->Id)->toBe('99')
        ->and($payload->NameOf->value)->toBe('Employee')
        ->and((string) $payload->EmployeeRef->value)->toBe('7')
        ->and($payload->EmployeeRef->name)->toBe('Jane Doe')
        ->and($payload->StartTime)->toBe('2026-07-27T09:00:00')
        ->and($payload->EndTime)->toBe('2026-07-27T17:00:00')
        ->and($payload->Description)->toBe('Client work')
        ->and((string) $payload->CustomerRef->value)->toBe('42')
        ->and($payload->CustomerRef->name)->toBe('Acme');
});

it('omits employee display name when quickbooks returns an empty label', function () {
    $captured = null;
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->with(Mockery::capture($captured))->andReturn((object) ['Id' => '99']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldReceive('employeeDisplayName')->once()->with(Mockery::type(QuickBooksToken::class), '7')->andReturn('');

    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('dataService')->andReturn($dataService);

    $snapshots = Mockery::mock(TimeActivitySnapshotService::class);
    $snapshots->shouldReceive('upsertFromQboEntity')->once();

    $service = new TimeActivityService(
        $quickBooks,
        new QboEmployeeAuthorizationService,
        new QuickBooksApiErrorFormatterService,
        $snapshots,
        Mockery::mock(TimeActivitySyncService::class),
        $displayNames,
    );

    $service->createForUser(makeUserWithEmployee(), QuickBooksToken::factory()->make(), [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
    ]);

    $payload = MockeryCapture::unwrap($captured);

    expect((string) $payload->EmployeeRef->value)->toBe('7')
        ->and(isset($payload->EmployeeRef->name))->toBeFalse();
});

it('upserts the local snapshot from the quickbooks create response', function () {
    $activity = (object) [
        'Id' => '99',
        'EmployeeRef' => (object) ['value' => '7'],
        'StartTime' => '2026-07-27T09:00:00',
        'EndTime' => '2026-07-27T17:00:00',
        'TxnDate' => '2026-07-27',
    ];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn($activity);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('dataService')->andReturn($dataService);

    $snapshots = Mockery::mock(TimeActivitySnapshotService::class);
    $snapshots->shouldReceive('upsertFromQboEntity')
        ->once()
        ->with(
            Mockery::type('string'),
            $dataService,
            $activity,
            false,
        );

    $service = new TimeActivityService(
        $quickBooks,
        new QboEmployeeAuthorizationService,
        new QuickBooksApiErrorFormatterService,
        $snapshots,
        Mockery::mock(TimeActivitySyncService::class),
        makeDisplayNamesMock(),
    );

    $service->createForUser(makeUserWithEmployee(), QuickBooksToken::factory()->make(), [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
    ]);
});

it('falls back to syncOneById when the quickbooks create response is sparse', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '88']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('dataService')->andReturn($dataService);

    $snapshots = Mockery::mock(TimeActivitySnapshotService::class);
    $snapshots->shouldReceive('upsertFromQboEntity')
        ->once()
        ->andThrow(new InvalidArgumentException('Time activity is missing EmployeeRef.'));

    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('syncOneById')
        ->once()
        ->with(Mockery::type(QuickBooksToken::class), '88', false);

    $service = new TimeActivityService(
        $quickBooks,
        new QboEmployeeAuthorizationService,
        new QuickBooksApiErrorFormatterService,
        $snapshots,
        $sync,
        makeDisplayNamesMock(),
    );

    $service->createForUser(makeUserWithEmployee(), QuickBooksToken::factory()->make(), [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
    ]);
});

it('upserts snapshots from numeric quickbooks create ids without an extra find call', function () {
    $activity = (object) [
        'Id' => 99,
        'EmployeeRef' => (object) ['value' => '7'],
        'StartTime' => '2026-07-27T09:00:00',
        'EndTime' => '2026-07-27T17:00:00',
        'TxnDate' => '2026-07-27',
    ];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn($activity);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('dataService')->andReturn($dataService);

    $snapshots = Mockery::mock(TimeActivitySnapshotService::class);
    $snapshots->shouldReceive('upsertFromQboEntity')
        ->once()
        ->with(
            Mockery::type('string'),
            $dataService,
            $activity,
            false,
        );

    $service = new TimeActivityService(
        $quickBooks,
        new QboEmployeeAuthorizationService,
        new QuickBooksApiErrorFormatterService,
        $snapshots,
        Mockery::mock(TimeActivitySyncService::class),
        makeDisplayNamesMock(),
    );

    $service->createForUser(makeUserWithEmployee(), QuickBooksToken::factory()->make(), [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
    ]);
});

it('omits optional customer and description fields when absent', function () {
    $captured = null;
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->with(Mockery::capture($captured))->andReturn((object) ['Id' => '56']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => '7',
    ]);
    $token = QuickBooksToken::factory()->make();

    $service->createForUser($user, $token, [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
        'description' => null,
        'customer_ref' => '',
    ]);

    $payload = MockeryCapture::unwrap($captured);

    expect((string) $payload->EmployeeRef->value)->toBe('7')
        ->and($payload->EmployeeRef->name ?? null)->toBe('Jane Doe')
        ->and(isset($payload->Description))->toBeFalse()
        ->and(isset($payload->CustomerRef))->toBeFalse();
});

it('maps billable status to quickbooks billable status values', function () {
    $captured = null;
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->with(Mockery::capture($captured))->andReturn((object) ['Id' => '57']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    $service->createForUser($user, $token, [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
        'is_billable' => true,
    ]);

    expect(MockeryCapture::unwrap($captured)->BillableStatus->value)->toBe('Billable');
});

it('maps non-billable status to quickbooks billable status values', function () {
    $captured = null;
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->with(Mockery::capture($captured))->andReturn((object) ['Id' => '58']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    $service->createForUser($user, $token, [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
        'is_billable' => false,
    ]);

    expect(MockeryCapture::unwrap($captured)->BillableStatus->value)->toBe('NotBillable');
});

it('finds a time activity for a user', function () {
    $activity = (object) [
        'Id' => '10',
        'SyncToken' => '0',
        'EmployeeRef' => (object) ['value' => '7'],
    ];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('TimeActivity', '10')->andReturn($activity);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    expect($service->findForUser($user, $token, '10')->Id)->toBe('10');
});

it('rejects invalid end time on update', function () {
    $existing = new IPPTimeActivity;
    $existing->Id = '10';
    $existing->SyncToken = '0';
    $existing->StartTime = '2026-07-27T17:00:00';
    $existing->EndTime = '2026-07-27T17:00:00';
    $existing->EmployeeRef = (object) ['value' => '7'];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($existing);
    $dataService->shouldNotReceive('Update');
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    try {
        $service->updateForUser($user, $token, '10', [
            'end_time' => '2026-07-27T09:00:00',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => TimeActivityTimeValidation::endAfterStartMessage(),
                'errors' => ['end_time' => [TimeActivityTimeValidation::endAfterStartMessage()]],
            ]);
    }
});

it('rejects invalid start time on update using the existing end time', function () {
    $existing = new IPPTimeActivity;
    $existing->Id = '10';
    $existing->SyncToken = '0';
    $existing->EndTime = '2026-07-27T09:00:00';
    $existing->EmployeeRef = (object) ['value' => '7'];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($existing);
    $dataService->shouldNotReceive('Update');
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    try {
        $service->updateForUser($user, $token, '10', [
            'start_time' => '2026-07-27T17:00:00',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => TimeActivityTimeValidation::endAfterStartMessage(),
                'errors' => ['end_time' => [TimeActivityTimeValidation::endAfterStartMessage()]],
            ]);
    }
});

it('requires sync token in the update payload', function () {
    $existing = new IPPTimeActivity;
    $existing->Id = '10';
    $existing->SyncToken = '4';
    $existing->StartTime = '2026-07-27T09:00:00';
    $existing->EndTime = '2026-07-27T17:00:00';
    $existing->EmployeeRef = (object) ['value' => '7'];

    $captured = null;
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($existing);
    $dataService->shouldReceive('Update')->once()->with(Mockery::capture($captured))->andReturn((object) ['Id' => '10']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    $service->updateForUser($user, $token, '10', [
        'description' => 'Sync token required',
    ]);

    expect(MockeryCapture::unwrap($captured)->SyncToken)->toBe('4');
});

it('updates a time activity using existing times when only description changes', function () {
    $existing = new IPPTimeActivity;
    $existing->Id = '10';
    $existing->SyncToken = '0';
    $existing->StartTime = '2026-07-27T09:00:00';
    $existing->EndTime = '2026-07-27T17:00:00';
    $existing->EmployeeRef = (object) ['value' => '7'];

    $captured = null;
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($existing);
    $dataService->shouldReceive('Update')->once()->with(Mockery::capture($captured))->andReturn((object) ['Id' => '10']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    $service->updateForUser($user, $token, '10', [
        'description' => 'Notes only',
    ]);

    $payload = MockeryCapture::unwrap($captured);

    expect($payload->Description)->toBe('Notes only')
        ->and($payload->StartTime)->toBe('2026-07-27T09:00:00')
        ->and($payload->EndTime)->toBe('2026-07-27T17:00:00');
});

it('rejects equal start and end times on update', function () {
    $existing = new IPPTimeActivity;
    $existing->Id = '10';
    $existing->SyncToken = '0';
    $existing->StartTime = '2026-07-27T09:00:00';
    $existing->EndTime = '2026-07-27T17:00:00';
    $existing->EmployeeRef = (object) ['value' => '7'];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($existing);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    try {
        $service->updateForUser($user, $token, '10', [
            'start_time' => '2026-07-27T10:00:00',
            'end_time' => '2026-07-27T10:00:00',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422);
    }
});

it('deletes a time activity for a user', function () {
    $existing = (object) [
        'Id' => '10',
        'EmployeeRef' => (object) ['value' => '7'],
    ];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($existing);
    $dataService->shouldReceive('Delete')->once()->with($existing);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('dataService')->andReturn($dataService);

    $snapshots = Mockery::mock(TimeActivitySnapshotService::class);
    $snapshots->shouldReceive('softDeleteByQboId')
        ->once()
        ->with('realm-1', '10');

    $service = new TimeActivityService(
        $quickBooks,
        new QboEmployeeAuthorizationService,
        new QuickBooksApiErrorFormatterService,
        $snapshots,
        Mockery::mock(TimeActivitySyncService::class),
        makeDisplayNamesMock(),
    );

    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-1']);

    $service->deleteForUser($user, $token, '10');
});

it('syncs the local snapshot after updating a time activity', function () {
    $existing = new IPPTimeActivity;
    $existing->Id = '10';
    $existing->SyncToken = '0';
    $existing->StartTime = '2026-07-27T09:00:00';
    $existing->EndTime = '2026-07-27T17:00:00';
    $existing->EmployeeRef = (object) ['value' => '7'];

    $updated = (object) ['Id' => '10', 'EmployeeRef' => (object) ['value' => '7']];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($existing);
    $dataService->shouldReceive('Update')->once()->andReturn($updated);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('dataService')->andReturn($dataService);

    $snapshots = Mockery::mock(TimeActivitySnapshotService::class);
    $snapshots->shouldReceive('upsertFromQboEntity')
        ->once()
        ->with(
            Mockery::type('string'),
            $dataService,
            Mockery::on(fn (object $activity): bool => (string) ($activity->Id ?? '') === '10'
                && isset($activity->EmployeeRef)),
            false,
        );

    $service = new TimeActivityService(
        $quickBooks,
        new QboEmployeeAuthorizationService,
        new QuickBooksApiErrorFormatterService,
        $snapshots,
        Mockery::mock(TimeActivitySyncService::class),
        makeDisplayNamesMock(),
    );

    $service->updateForUser(makeUserWithEmployee(), QuickBooksToken::factory()->make(), '10', [
        'description' => 'Updated notes',
    ]);
});

it('creates a time activity without optional fields', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '55']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => '7',
    ]);
    $token = QuickBooksToken::factory()->make();

    $result = $service->createForUser($user, $token, [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
    ]);

    expect($result->Id)->toBe('55');
});

it('upserts snapshots from the quickbooks update response without a second find call', function () {
    $activity = (object) [
        'Id' => 77,
        'EmployeeRef' => (object) ['value' => '7'],
        'StartTime' => '2026-07-27T09:00:00',
        'EndTime' => '2026-07-27T17:00:00',
        'TxnDate' => '2026-07-27',
    ];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn($activity);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('dataService')->andReturn($dataService);

    $snapshots = Mockery::mock(TimeActivitySnapshotService::class);
    $snapshots->shouldReceive('upsertFromQboEntity')
        ->once()
        ->with(
            Mockery::type('string'),
            $dataService,
            $activity,
            false,
        );

    $service = new TimeActivityService(
        $quickBooks,
        new QboEmployeeAuthorizationService,
        new QuickBooksApiErrorFormatterService,
        $snapshots,
        Mockery::mock(TimeActivitySyncService::class),
        makeDisplayNamesMock(),
    );

    $service->createForUser(makeUserWithEmployee(), QuickBooksToken::factory()->make(), [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
    ]);
});

it('aborts when creating a time activity fails in quickbooks', function () {
    $error = Mockery::mock();
    $error->shouldReceive('getResponseBody')->andReturn('create failed');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '99']);
    $dataService->shouldReceive('getLastError')->andReturn($error);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    $service->createForUser($user, $token, [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
    ]);
})->throws(HttpResponseException::class);

it('updates a time activity for a user', function () {
    $existing = new IPPTimeActivity;
    $existing->Id = '10';
    $existing->SyncToken = '0';
    $existing->StartTime = '2026-07-27T09:00:00';
    $existing->EndTime = '2026-07-27T17:00:00';
    $existing->EmployeeRef = (object) ['value' => '7'];

    $captured = null;
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($existing);
    $dataService->shouldReceive('Update')->once()->with(Mockery::capture($captured))->andReturn((object) ['Id' => '10']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    $result = $service->updateForUser($user, $token, '10', [
        'description' => 'Updated notes',
        'start_time' => '2026-07-27T10:00:00',
        'end_time' => '2026-07-27T18:00:00',
    ]);

    $payload = MockeryCapture::unwrap($captured);

    expect($result->Id)->toBe('10')
        ->and($payload->Id->value)->toBe('10')
        ->and($payload->SyncToken)->toBe('0')
        ->and($payload->StartTime)->toBe('2026-07-27T10:00:00')
        ->and($payload->EndTime)->toBe('2026-07-27T18:00:00')
        ->and($payload->Description)->toBe('Updated notes');
});

it('aborts when updating a time activity fails in quickbooks', function () {
    $existing = new IPPTimeActivity;
    $existing->Id = '10';
    $existing->SyncToken = '0';
    $existing->StartTime = '2026-07-27T09:00:00';
    $existing->EndTime = '2026-07-27T17:00:00';
    $existing->EmployeeRef = (object) ['value' => '7'];
    $error = Mockery::mock();
    $error->shouldReceive('getResponseBody')->andReturn('update failed');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($existing);
    $dataService->shouldReceive('Update')->once()->andReturn((object) ['Id' => '10']);
    $dataService->shouldReceive('getLastError')->andReturn(null, $error);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    $service->updateForUser($user, $token, '10', [
        'end_time' => '2026-07-27T18:00:00',
    ]);
})->throws(HttpResponseException::class);

it('aborts when the time activity is missing', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    try {
        $service->findForUser($user, $token, '404');
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(404)
            ->and($exception->getResponse()->getData(true))->toBe(['message' => 'Time activity not found']);
    }
});

it('aborts when a time activity belongs to another employee', function () {
    $activity = (object) [
        'Id' => '10',
        'SyncToken' => '0',
        'EmployeeRef' => (object) ['value' => '99'],
    ];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($activity);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    try {
        $service->findForUser($user, $token, '10');
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(404)
            ->and($exception->getResponse()->getData(true))->toBe(['message' => 'Time activity not found']);
    }
});

it('aborts when deleting a time activity fails in quickbooks', function () {
    $existing = (object) [
        'Id' => '10',
        'EmployeeRef' => (object) ['value' => '7'],
    ];
    $error = Mockery::mock();
    $error->shouldReceive('getResponseBody')->andReturn('delete failed');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($existing);
    $dataService->shouldReceive('Delete')->once()->with($existing);
    $dataService->shouldReceive('getLastError')->andReturn(null, $error);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    $service->deleteForUser($user, $token, '10');
})->throws(HttpResponseException::class);
