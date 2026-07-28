<?php

use App\Models\QuickBooksToken;
use App\Services\QboListCacheService;
use App\Services\QboServiceListService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use Illuminate\Http\Exceptions\HttpResponseException;
use QuickBooksOnline\API\DataService\DataService;

covers(QboServiceListService::class);

/**
 * @return QboServiceListService
 */
function makeQboServiceListService(QuickBooksService $quickBooks): QboServiceListService
{
    return new QboServiceListService(
        $quickBooks,
        new QboListCacheService,
        new QuickBooksApiErrorFormatterService,
    );
}

it('caches service lists per quickbooks realm', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) ['Id' => '33', 'Name' => 'Consulting'],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboServiceListService($quickBooks);

    expect($service->listActive($token))->toBe([
        ['id' => '33', 'display_name' => 'Consulting'],
    ])->and($service->listActive($token))->toHaveCount(1);
});

it('bypasses the cache when refresh is requested', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->twice()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->twice()->with($token)->andReturn($dataService);

    $service = makeQboServiceListService($quickBooks);

    $service->listActive($token);
    $service->listActive($token, true);
});

it('normalizes services missing optional quickbooks fields', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([(object) []]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboServiceListService($quickBooks);

    expect($service->listActive($token))->toBe([
        ['id' => '', 'display_name' => ''],
    ]);
});

it('aborts when quickbooks service list query fails', function () {
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

    $service = makeQboServiceListService($quickBooks);

    expect(fn () => $service->listActive($token))
        ->toThrow(HttpResponseException::class);
});
