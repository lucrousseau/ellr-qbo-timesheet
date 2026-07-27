<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboEmployeeAuthorizationService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use App\Services\TimeActivityListService;
use Illuminate\Http\Exceptions\HttpResponseException;
use QuickBooksOnline\API\DataService\DataService;

covers(TimeActivityListService::class);

const LIST_PAGE_QUERY = "SELECT * FROM TimeActivity WHERE EmployeeRef = '7' STARTPOSITION 1 MAXRESULTS 100";
const LIST_PROBE_QUERY = "SELECT * FROM TimeActivity WHERE EmployeeRef = '7' STARTPOSITION 101 MAXRESULTS 1";

function makeTimeActivityListService(DataService $dataService): TimeActivityListService
{
    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('dataService')->andReturn($dataService);

    return new TimeActivityListService($quickBooks, new QboEmployeeAuthorizationService, new QuickBooksApiErrorFormatterService);
}

it('lists time activities for a user', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(LIST_PAGE_QUERY)
        ->andReturn([(object) ['Id' => '1']]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityListService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->make();

    $result = $service->listForUser($user, $token);

    expect($result['data'])->toHaveCount(1)
        ->and($result['meta']['truncated'])->toBeFalse();
});

it('clamps invalid pagination arguments before querying quickbooks', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with("SELECT * FROM TimeActivity WHERE EmployeeRef = '7' STARTPOSITION 1 MAXRESULTS 1")
        ->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityListService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->make();

    $result = $service->listForUser($user, $token, 0, 0);

    expect($result['meta']['start_position'])->toBe(1)
        ->and($result['meta']['max_results'])->toBe(1);
});

it('marks list results as truncated when a probe row exists', function () {
    $dataService = Mockery::mock(DataService::class);
    $rows = array_map(fn (int $id) => (object) ['Id' => (string) $id], range(1, 100));
    $dataService->shouldReceive('Query')
        ->once()
        ->with(LIST_PAGE_QUERY)
        ->andReturn($rows);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(LIST_PROBE_QUERY)
        ->andReturn([(object) ['Id' => '101']]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityListService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->make();

    $result = $service->listForUser($user, $token);

    expect($result['meta']['count'])->toBe(100)
        ->and($result['meta']['truncated'])->toBeTrue();
});

it('marks list results as truncated without a probe when probing is disabled', function () {
    config(['quickbooks.time_activities_probe_truncated' => false]);

    $dataService = Mockery::mock(DataService::class);
    $rows = array_map(fn (int $id) => (object) ['Id' => (string) $id], range(1, 100));
    $dataService->shouldReceive('Query')
        ->once()
        ->with(LIST_PAGE_QUERY)
        ->andReturn($rows);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityListService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->make();

    $result = $service->listForUser($user, $token);

    expect($result['meta']['truncated'])->toBeTrue();
});

it('does not mark list results as truncated when the probe finds no rows', function () {
    $dataService = Mockery::mock(DataService::class);
    $rows = array_map(fn (int $id) => (object) ['Id' => (string) $id], range(1, 100));
    $dataService->shouldReceive('Query')
        ->once()
        ->with(LIST_PAGE_QUERY)
        ->andReturn($rows);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(LIST_PROBE_QUERY)
        ->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityListService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->make();

    $result = $service->listForUser($user, $token);

    expect($result['meta']['count'])->toBe(100)
        ->and($result['meta']['truncated'])->toBeFalse();
});

it('escapes single quotes in employee refs for qbo queries', function () {
    $maliciousRef = "7' OR '1'='1";
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with("SELECT * FROM TimeActivity WHERE EmployeeRef = '7\\' OR \\'1\\'=\\'1' STARTPOSITION 1 MAXRESULTS 100")
        ->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityListService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => $maliciousRef,
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->make();

    expect($service->listForUser($user, $token)['data'])->toBe([]);
});

it('returns an empty list when quickbooks returns a non-array result', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = makeTimeActivityListService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->make();

    expect($service->listForUser($user, $token)['data'])->toBe([]);
});

it('aborts when listing time activities fails in quickbooks', function () {
    $error = Mockery::mock();
    $error->shouldReceive('getResponseBody')->andReturn('query failed');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn($error);

    $service = makeTimeActivityListService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->make();

    $service->listForUser($user, $token);
})->throws(HttpResponseException::class);

it('aborts when the truncated probe query fails in quickbooks', function () {
    $error = Mockery::mock();
    $error->shouldReceive('getResponseBody')->andReturn('probe failed');

    $rows = array_map(fn (int $id) => (object) ['Id' => (string) $id], range(1, 100));
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(LIST_PAGE_QUERY)
        ->andReturn($rows);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(LIST_PROBE_QUERY)
        ->andReturn([]);
    $dataService->shouldReceive('getLastError')
        ->andReturn(null, $error);

    $service = makeTimeActivityListService($dataService);
    $user = User::factory()->make([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $token = QuickBooksToken::factory()->make();

    $service->listForUser($user, $token);
})->throws(HttpResponseException::class);

it('aborts when the qbo employee is missing', function () {
    $service = makeTimeActivityListService(Mockery::mock(DataService::class));
    $user = User::factory()->make(['qbo_employee_ref' => null]);
    $token = QuickBooksToken::factory()->make();

    $service->listForUser($user, $token);
})->throws(HttpResponseException::class);
