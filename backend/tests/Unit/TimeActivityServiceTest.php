<?php

use App\Http\Requests\StoreTimeActivityRequest;
use App\Http\Requests\UpdateTimeActivityRequest;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use App\Services\TimeActivityService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use QuickBooksOnline\API\DataService\DataService;

function makeTimeActivityService(DataService $dataService): TimeActivityService
{
    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('dataService')->andReturn($dataService);

    return new TimeActivityService($quickBooks);
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

it('lists time activities for a user', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([(object) ['Id' => '1']]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    expect($service->listForUser($user, $token))->toHaveCount(1);
});

it('creates a time activity for a user', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '99']);
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

    expect($result->Id)->toBe('99');
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
    $existing = (object) [
        'Id' => '10',
        'SyncToken' => '0',
        'StartTime' => '2026-07-27T17:00:00',
        'EndTime' => '2026-07-27T17:00:00',
        'EmployeeRef' => (object) ['value' => '7'],
    ];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn($existing);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityService($dataService);
    $user = makeUserWithEmployee();
    $token = QuickBooksToken::factory()->make();

    $service->updateForUser($user, $token, '10', [
        'end_time' => '2026-07-27T09:00:00',
    ]);
})->throws(HttpResponseException::class);

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

it('aborts when the qbo employee is missing', function () {
    $service = makeTimeActivityService(Mockery::mock(DataService::class));
    $user = User::factory()->make(['qbo_employee_ref' => null]);
    $token = QuickBooksToken::factory()->make();

    $service->listForUser($user, $token);
})->throws(HttpResponseException::class);
