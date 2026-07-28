<?php

use App\Services\QboListCacheService;
use Illuminate\Support\Facades\Cache;

covers(QboListCacheService::class);

it('returns configured list cache ttl and max results', function () {
    config([
        'quickbooks.list_cache_ttl_minutes' => 20,
        'quickbooks.list_max_results' => 500,
    ]);

    $service = new QboListCacheService;

    expect($service->ttlMinutes())->toBe(20)
        ->and($service->maxResults())->toBe(500);
});

it('caches quickbooks list data per resource and realm', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $service = new QboListCacheService;
    $calls = 0;

    $first = $service->remember(
        QboListCacheService::RESOURCE_EMPLOYEES,
        'realm-42',
        false,
        function () use (&$calls): array {
            $calls++;

            return [['id' => '7']];
        },
    );

    $second = $service->remember(
        QboListCacheService::RESOURCE_EMPLOYEES,
        'realm-42',
        false,
        function () use (&$calls): array {
            $calls++;

            return [['id' => '99']];
        },
    );

    expect($first)->toBe([['id' => '7']])
        ->and($second)->toBe([['id' => '7']])
        ->and($calls)->toBe(1);
});

it('bypasses cached quickbooks lists when refresh is requested', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $service = new QboListCacheService;
    $calls = 0;

    $service->remember(
        QboListCacheService::RESOURCE_EMPLOYEES,
        'realm-42',
        false,
        function () use (&$calls): array {
            $calls++;

            return [['id' => '7']];
        },
    );

    $refreshed = $service->remember(
        QboListCacheService::RESOURCE_EMPLOYEES,
        'realm-42',
        true,
        function () use (&$calls): array {
            $calls++;

            return [['id' => '8']];
        },
    );

    expect($refreshed)->toBe([['id' => '8']])
        ->and($calls)->toBe(2);
});

it('does not cache empty quickbooks lists', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $service = new QboListCacheService;
    $calls = 0;

    $service->remember(
        QboListCacheService::RESOURCE_EMPLOYEES,
        'realm-42',
        false,
        function () use (&$calls): array {
            $calls++;

            return [];
        },
    );

    $service->remember(
        QboListCacheService::RESOURCE_EMPLOYEES,
        'realm-42',
        false,
        function () use (&$calls): array {
            $calls++;

            return [];
        },
    );

    expect($calls)->toBe(2);
});

it('forgets all cached quickbooks lists for a realm', function () {
    $service = new QboListCacheService;

    Cache::put($service->cacheKey(QboListCacheService::RESOURCE_EMPLOYEES, 'realm-42'), [['id' => '7']], now()->addHour());

    $service->forgetRealm('realm-42');

    expect(Cache::has($service->cacheKey(QboListCacheService::RESOURCE_EMPLOYEES, 'realm-42')))->toBeFalse();
});

it('clamps invalid quickbooks list configuration values', function () {
    config([
        'quickbooks.list_cache_ttl_minutes' => -5,
        'quickbooks.list_max_results' => 0,
    ]);

    $service = new QboListCacheService;

    expect($service->ttlMinutes())->toBe(0)
        ->and($service->maxResults())->toBe(1);
});

it('uses configured defaults when quickbooks list settings are absent', function () {
    $quickbooks = config('quickbooks');
    unset($quickbooks['list_cache_ttl_minutes'], $quickbooks['list_max_results']);
    config(['quickbooks' => $quickbooks]);

    $service = new QboListCacheService;

    expect($service->ttlMinutes())->toBe(15)
        ->and($service->maxResults())->toBe(1000);
});

it('forgets cached data before refreshing quickbooks lists', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $service = new QboListCacheService;
    $cacheKey = $service->cacheKey(QboListCacheService::RESOURCE_EMPLOYEES, 'realm-42');

    Cache::put($cacheKey, [['id' => 'stale']], now()->addHour());
    Cache::spy();

    $refreshed = $service->remember(
        QboListCacheService::RESOURCE_EMPLOYEES,
        'realm-42',
        true,
        fn (): array => [['id' => 'fresh']],
    );

    expect($refreshed)->toBe([['id' => 'fresh']]);
    Cache::shouldHaveReceived('forget')->once()->with($cacheKey);
});

it('casts non-numeric quickbooks list configuration values to integers', function () {
    config([
        'quickbooks.list_cache_ttl_minutes' => 'invalid',
        'quickbooks.list_max_results' => 'invalid',
    ]);

    $service = new QboListCacheService;

    expect($service->ttlMinutes())->toBe(0)
        ->and($service->maxResults())->toBe(1);
});

it('fetches lists without caching when ttl is zero', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $service = new QboListCacheService;
    $calls = 0;

    $service->remember(
        QboListCacheService::RESOURCE_EMPLOYEES,
        'realm-42',
        false,
        function () use (&$calls): array {
            $calls++;

            return [['id' => '1']];
        },
    );

    $service->remember(
        QboListCacheService::RESOURCE_EMPLOYEES,
        'realm-42',
        false,
        function () use (&$calls): array {
            $calls++;

            return [['id' => '2']];
        },
    );

    expect($calls)->toBe(2);
});

it('forgets a single cached quickbooks list resource', function () {
    $service = new QboListCacheService;
    $cacheKey = $service->cacheKey(QboListCacheService::RESOURCE_EMPLOYEES, 'realm-42');

    Cache::put($cacheKey, [['id' => '7']], now()->addHour());

    $service->forget(QboListCacheService::RESOURCE_EMPLOYEES, 'realm-42');

    expect(Cache::has($cacheKey))->toBeFalse();
});
