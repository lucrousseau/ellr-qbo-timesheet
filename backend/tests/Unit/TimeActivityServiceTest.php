<?php

use App\Http\Requests\StoreTimeActivityRequest;
use App\Http\Requests\UpdateTimeActivityRequest;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboEmployeeAuthorizationService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use App\Services\TimeActivityService;
use App\Support\TimeActivityTimeValidation;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use QuickBooksOnline\API\Data\IPPTimeActivity;
use QuickBooksOnline\API\DataService\DataService;
use Tests\Support\MockeryCapture;

covers(TimeActivityService::class);

function makeTimeActivityService(DataService $dataService): TimeActivityService
{
    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('dataService')->andReturn($dataService);
    $employeeAuth = new QboEmployeeAuthorizationService;
    $apiErrors = new QuickBooksApiErrorFormatterService;

    return new TimeActivityService($quickBooks, $employeeAuth, $apiErrors);
}

function makeUserWithEmployee(): User
{
    return User::factory()->make([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
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
                'message' => TimeActivityTimeValidation::INCOMPLETE_TIME_FIELDS_MESSAGE,
                'errors' => [
                    'start_time' => [TimeActivityTimeValidation::INCOMPLETE_TIME_FIELDS_MESSAGE],
                    'end_time' => [TimeActivityTimeValidation::INCOMPLETE_TIME_FIELDS_MESSAGE],
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

it('omits optional customer and description fields when absent', function () {
    $captured = null;
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->with(Mockery::capture($captured))->andReturn((object) ['Id' => '56']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => null,
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
        ->and(isset($payload->EmployeeRef->name))->toBeFalse()
        ->and(isset($payload->Description))->toBeFalse()
        ->and(isset($payload->CustomerRef))->toBeFalse();
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
                'message' => TimeActivityTimeValidation::END_AFTER_START_MESSAGE,
                'errors' => ['end_time' => [TimeActivityTimeValidation::END_AFTER_START_MESSAGE]],
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
                'message' => TimeActivityTimeValidation::END_AFTER_START_MESSAGE,
                'errors' => ['end_time' => [TimeActivityTimeValidation::END_AFTER_START_MESSAGE]],
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

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    $service->deleteForUser($user, $token, '10');

    expect(true)->toBeTrue();
});

it('creates a time activity without optional fields', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '55']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => null,
    ]);
    $token = QuickBooksToken::factory()->make();

    $result = $service->createForUser($user, $token, [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
    ]);

    expect($result->Id)->toBe('55');
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
