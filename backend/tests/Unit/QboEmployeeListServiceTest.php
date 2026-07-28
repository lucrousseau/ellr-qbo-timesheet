<?php

use App\Models\QuickBooksToken;
use App\Services\QboEmployeeListService;
use App\Services\QboListCacheService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use QuickBooksOnline\API\DataService\DataService;

covers(QboEmployeeListService::class);

/**
 * @return QboEmployeeListService
 */
function makeQboEmployeeListService(QuickBooksService $quickBooks): QboEmployeeListService
{
    return new QboEmployeeListService(
        $quickBooks,
        new QboListCacheService,
        new QuickBooksApiErrorFormatterService,
    );
}

it('caches employee lists per quickbooks realm', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([
        (object) [
            'Id' => '7',
            'DisplayName' => 'Jane Doe',
            'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
        ],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeListService($quickBooks);

    expect($service->listActive($token))->toHaveCount(1)
        ->and($service->listActive($token))->toHaveCount(1);
});

it('bypasses the cache when refresh is requested', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->twice()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->twice()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeListService($quickBooks);

    $service->listActive($token);
    $service->listActive($token, true);
});

it('forgets cached employee lists for a realm', function () {
    Cache::put(QboEmployeeListService::cacheKeyForRealm('realm-42'), [['id' => '7']], now()->addHour());

    (new QboEmployeeListService(
        Mockery::mock(QuickBooksService::class),
        new QboListCacheService,
        new QuickBooksApiErrorFormatterService,
    ))->forgetCachedForRealm('realm-42');

    expect(Cache::has(QboEmployeeListService::cacheKeyForRealm('realm-42')))->toBeFalse();
});

it('normalizes a single quickbooks employee entity from query results', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn(
        (object) [
            'Id' => '7',
            'DisplayName' => 'Jane Doe',
            'PrimaryEmailAddr' => 'jane@example.com',
        ],
    );
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeListService($quickBooks);

    expect($service->listActive($token))->toMatchArray([
        [
            'id' => '7',
            'display_name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ],
    ]);
});

it('does not cache empty employee lists', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->twice()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->twice()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeListService($quickBooks);

    $service->listActive($token);
    $service->listActive($token);
});

it('aborts when quickbooks employee list query fails', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $error = Mockery::mock();
    $error->shouldReceive('getHttpStatusCode')->andReturn(400);
    $error->shouldReceive('getResponseBody')->andReturn('query failed');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn($error);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeListService($quickBooks);

    try {
        $service->listActive($token);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBeGreaterThanOrEqual(400);
    }
});

it('probes quickbooks employee list access without using the cache', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    makeQboEmployeeListService($quickBooks)->assertCanListEmployees($token);
});

it('normalizes employees missing optional quickbooks fields', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([(object) []]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeListService($quickBooks);

    expect($service->listActive($token))->toBe([
        [
            'id' => '',
            'display_name' => '',
            'email' => null,
        ],
    ]);
});

it('reindexes associative quickbooks employee query results', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([
        'first' => (object) [
            'Id' => '1',
            'DisplayName' => 'Alice',
            'PrimaryEmailAddr' => (object) ['Address' => 'alice@example.com'],
        ],
        'second' => (object) [
            'Id' => '2',
            'DisplayName' => 'Bob',
            'PrimaryEmailAddr' => (object) ['Address' => 'bob@example.com'],
        ],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeListService($quickBooks);

    expect($service->listActive($token))->toBe([
        [
            'id' => '1',
            'display_name' => 'Alice',
            'email' => 'alice@example.com',
        ],
        [
            'id' => '2',
            'display_name' => 'Bob',
            'email' => 'bob@example.com',
        ],
    ]);
});

it('casts numeric quickbooks employee ids to strings in list results', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->once()->andReturn([
        (object) [
            'Id' => 7,
            'DisplayName' => 123,
            'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
        ],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeListService($quickBooks);

    expect($service->listActive($token))->toBe([
        [
            'id' => '7',
            'display_name' => '123',
            'email' => 'jane@example.com',
        ],
    ]);
});
