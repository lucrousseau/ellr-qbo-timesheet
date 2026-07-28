<?php

use App\Models\QuickBooksToken;
use App\Services\QboListCacheService;
use App\Services\QboProjectListService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use Illuminate\Http\Exceptions\HttpResponseException;
use QuickBooksOnline\API\DataService\DataService;

covers(QboProjectListService::class);

/**
 * @return QboProjectListService
 */
function makeQboProjectListService(QuickBooksService $quickBooks): QboProjectListService
{
    return new QboProjectListService(
        $quickBooks,
        new QboListCacheService,
        new QuickBooksApiErrorFormatterService,
    );
}

it('caches project lists per customer and quickbooks realm', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/ParentRef = '11'/"))
        ->andReturn([
            (object) ['Id' => '22', 'DisplayName' => 'Website redesign'],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboProjectListService($quickBooks);

    expect($service->listForCustomer($token, '11'))->toBe([
        ['id' => '22', 'display_name' => 'Website redesign'],
    ])->and($service->listForCustomer($token, '11'))->toHaveCount(1);
});

it('bypasses the cache when refresh is requested', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->twice()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->twice()->with($token)->andReturn($dataService);

    $service = makeQboProjectListService($quickBooks);

    $service->listForCustomer($token, '11');
    $service->listForCustomer($token, '11', true);
});

it('normalizes projects missing optional quickbooks fields', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([(object) []]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboProjectListService($quickBooks);

    expect($service->listForCustomer($token, '11'))->toBe([
        ['id' => '', 'display_name' => ''],
    ]);
});

it('aborts when quickbooks project list query fails', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $error = Mockery::mock();
    $error->shouldReceive('getHttpStatusCode')->andReturn(400);
    $error->shouldReceive('getResponseBody')->andReturn('query failed');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn($error);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboProjectListService($quickBooks);

    expect(fn () => $service->listForCustomer($token, '11'))
        ->toThrow(HttpResponseException::class);
});
