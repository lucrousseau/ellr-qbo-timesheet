<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboCustomerListService;
use App\Services\QboEmployeeAuthorizationService;
use App\Services\QboListCacheService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use App\Support\QboCustomerResolver;
use Illuminate\Http\Exceptions\HttpResponseException;
use QuickBooksOnline\API\DataService\DataService;

covers(QboCustomerListService::class);

/**
 * @return QboCustomerListService
 */
function makeQboCustomerListService(QuickBooksService $quickBooks): QboCustomerListService
{
    return new QboCustomerListService(
        $quickBooks,
        new QboListCacheService,
        new QuickBooksApiErrorFormatterService,
        app(QboCustomerResolver::class),
        new QboEmployeeAuthorizationService,
    );
}

it('caches customer lists per employee and quickbooks realm', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $user = User::factory()->make(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/FROM TimeActivity WHERE EmployeeRef = '7'/"))
        ->andReturn([
            (object) ['CustomerRef' => (object) ['value' => '11']],
        ]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/FROM Customer WHERE Id IN \\('11'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '11',
                'DisplayName' => 'Acme Corp',
                'Job' => false,
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboCustomerListService($quickBooks);

    expect($service->listForUser($user, $token))->toHaveCount(1)
        ->and($service->listForUser($user, $token))->toHaveCount(1);
});

it('bypasses the cache when refresh is requested', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $user = User::factory()->make(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->twice()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->twice()->with($token)->andReturn($dataService);

    $service = makeQboCustomerListService($quickBooks);

    $service->listForUser($user, $token);
    $service->listForUser($user, $token, true);
});

it('collects project references from time activities', function () {
    config([
        'quickbooks.list_cache_ttl_minutes' => 0,
        'quickbooks.employee_customer_scan_max_pages' => 1,
        'quickbooks.time_activities_max_results' => 100,
    ]);

    $user = User::factory()->make(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) ['ProjectRef' => (object) ['value' => '22']],
        ]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/FROM Customer WHERE Id IN \\('22'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '22',
                'DisplayName' => 'Website redesign',
                'Job' => true,
                'ParentRef' => (object) ['value' => '11'],
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/FROM Customer WHERE Id IN \\('11'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '11',
                'DisplayName' => 'Acme Corp',
                'Job' => false,
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboCustomerListService($quickBooks);

    expect($service->listForUser($user, $token))->toBe([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);
});

it('paginates time activities until a short page is returned', function () {
    config([
        'quickbooks.list_cache_ttl_minutes' => 0,
        'quickbooks.employee_customer_scan_max_pages' => 3,
        'quickbooks.time_activities_max_results' => 1,
        'quickbooks.list_max_results' => 1000,
    ]);

    $user = User::factory()->make(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern('/STARTPOSITION 1 MAXRESULTS 1/'))
        ->andReturn([
            (object) ['CustomerRef' => (object) ['value' => '11']],
        ]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern('/STARTPOSITION 2 MAXRESULTS 1/'))
        ->andReturn([]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/FROM Customer WHERE Id IN \\('11'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '11',
                'DisplayName' => 'Acme Corp',
                'Job' => false,
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboCustomerListService($quickBooks);

    expect($service->listForUser($user, $token))->toHaveCount(1);
});

it('aborts when quickbooks customer scan query fails', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $user = User::factory()->make(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $error = Mockery::mock();
    $error->shouldReceive('getHttpStatusCode')->andReturn(400);
    $error->shouldReceive('getResponseBody')->andReturn('query failed');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn($error);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboCustomerListService($quickBooks);

    expect(fn () => $service->listForUser($user, $token))
        ->toThrow(HttpResponseException::class);
});

it('returns an empty customer list when no time activities exist', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $user = User::factory()->make(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    expect(makeQboCustomerListService($quickBooks)->listForUser($user, $token))->toBe([]);
});

it('stops scanning after the configured maximum number of pages', function () {
    config([
        'quickbooks.list_cache_ttl_minutes' => 0,
        'quickbooks.employee_customer_scan_max_pages' => 2,
        'quickbooks.time_activities_max_results' => 1,
        'quickbooks.list_max_results' => 1000,
    ]);

    $user = User::factory()->make(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->twice()
        ->andReturn(
            [(object) ['CustomerRef' => (object) ['value' => '11']]],
            [(object) ['CustomerRef' => (object) ['value' => '12']]],
        );
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern('/FROM Customer WHERE Id IN/'))
        ->andReturn([
            (object) ['Id' => '11', 'DisplayName' => 'Alpha', 'Job' => false, 'Active' => true],
            (object) ['Id' => '12', 'DisplayName' => 'Beta', 'Job' => false, 'Active' => true],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    expect(makeQboCustomerListService($quickBooks)->listForUser($user, $token))->toHaveCount(2);
});

it('uses configured page size and start positions when scanning activities', function () {
    config([
        'quickbooks.list_cache_ttl_minutes' => 0,
        'quickbooks.time_activities_max_results' => 2,
        'quickbooks.employee_customer_scan_max_pages' => 2,
        'quickbooks.list_max_results' => 5,
    ]);

    $user = User::factory()->make(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern('/STARTPOSITION 1 MAXRESULTS 2/'))
        ->andReturn([
            (object) ['CustomerRef' => (object) ['value' => '11']],
            (object) ['CustomerRef' => (object) ['value' => '12']],
        ]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern('/STARTPOSITION 3 MAXRESULTS 2/'))
        ->andReturn([]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern('/FROM Customer WHERE Id IN/'))
        ->andReturn([
            (object) ['Id' => '11', 'DisplayName' => 'Alpha', 'Job' => false, 'Active' => true],
            (object) ['Id' => '12', 'DisplayName' => 'Beta', 'Job' => false, 'Active' => true],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    expect(makeQboCustomerListService($quickBooks)->listForUser($user, $token))->toHaveCount(2);
});
