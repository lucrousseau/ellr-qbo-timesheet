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

it('forgets scoped quickbooks list resources', function () {
    $service = new QboListCacheService;
    $cacheKey = $service->cacheKey(QboListCacheService::RESOURCE_PROJECTS, 'realm-42', '11');

    Cache::put($cacheKey, [['id' => '22']], now()->addHour());

    $service->forget(QboListCacheService::RESOURCE_PROJECTS, 'realm-42', '11');

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('includes scoped suffixes in cache keys', function () {
    $service = new QboListCacheService;

    expect($service->cacheKey(QboListCacheService::RESOURCE_CUSTOMERS, 'realm-42', 'employee-7'))
        ->toEndWith(':7')
        ->and($service->cacheKey(QboListCacheService::RESOURCE_CUSTOMERS, 'realm-42', ''))
        ->not->toEndWith(':');
});

it('bumps the realm cache generation when forgetting a realm', function () {
    $service = new QboListCacheService;
    $realmId = 'realm-42';
    $scopedKey = $service->cacheKey(QboListCacheService::RESOURCE_PROJECTS, $realmId, '11');

    Cache::put($scopedKey, [['id' => '22']], now()->addHour());

    expect($service->realmVersion($realmId))->toBe(0);

    $service->forgetRealm($realmId);

    expect($service->realmVersion($realmId))->toBe(1)
        ->and($service->cacheKey(QboListCacheService::RESOURCE_PROJECTS, $realmId, '11'))
        ->not->toBe($scopedKey);
});

it('returns cached lists with scoped keys', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $service = new QboListCacheService;
    $calls = 0;

    $first = $service->remember(
        QboListCacheService::RESOURCE_PROJECTS,
        'realm-42',
        false,
        function () use (&$calls): array {
            $calls++;

            return [['id' => '22']];
        },
        '11',
    );

    $second = $service->remember(
        QboListCacheService::RESOURCE_PROJECTS,
        'realm-42',
        false,
        function () use (&$calls): array {
            $calls++;

            return [['id' => '99']];
        },
        '11',
    );

    expect($first)->toBe([['id' => '22']])
        ->and($second)->toBe([['id' => '22']])
        ->and($calls)->toBe(1);
});

it('forgets each quickbooks list resource when clearing a realm', function () {
    $service = new QboListCacheService;
    $realmId = 'realm-42';

    foreach ([
        QboListCacheService::RESOURCE_EMPLOYEES,
        QboListCacheService::RESOURCE_CUSTOMERS,
        QboListCacheService::RESOURCE_PROJECTS,
        QboListCacheService::RESOURCE_SERVICES,
    ] as $resource) {
        Cache::put($service->cacheKey($resource, $realmId), [['id' => '1']], now()->addHour());
    }

    $service->forgetRealm($realmId);

    foreach ([
        QboListCacheService::RESOURCE_EMPLOYEES,
        QboListCacheService::RESOURCE_CUSTOMERS,
        QboListCacheService::RESOURCE_PROJECTS,
        QboListCacheService::RESOURCE_SERVICES,
    ] as $resource) {
        expect(Cache::has($service->cacheKey($resource, $realmId, null)))->toBeFalse();
    }
});

it('refreshes lists without caching when ttl is zero', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $service = new QboListCacheService;
    $calls = 0;

    $refreshed = $service->remember(
        QboListCacheService::RESOURCE_SERVICES,
        'realm-42',
        true,
        function () use (&$calls): array {
            $calls++;

            return [['id' => '33']];
        },
    );

    expect($refreshed)->toBe([['id' => '33']])
        ->and($calls)->toBe(1);
});

it('builds versioned cache keys without a scope suffix', function () {
    $service = new QboListCacheService;

    expect($service->cacheKey(QboListCacheService::RESOURCE_SERVICES, 'realm-42'))
        ->toBe('quickbooks:v0:services:realm-42');
});

it('refetches lists when cached values are not arrays', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $service = new QboListCacheService;
    $cacheKey = $service->cacheKey(QboListCacheService::RESOURCE_EMPLOYEES, 'realm-42');
    Cache::put($cacheKey, 'stale-string', now()->addHour());

    $calls = 0;
    $data = $service->remember(
        QboListCacheService::RESOURCE_EMPLOYEES,
        'realm-42',
        false,
        function () use (&$calls): array {
            $calls++;

            return [['id' => '7']];
        },
    );

    expect($data)->toBe([['id' => '7']])
        ->and($calls)->toBe(1);
});

it('forgets every quickbooks list resource when clearing a realm', function () {
    Cache::spy();

    app(QboListCacheService::class)->forgetRealm('realm-42');

    Cache::shouldHaveReceived('forget')->times(4);
});

it('treats negative realm versions as zero', function () {
    $service = new QboListCacheService;
    Cache::forever('quickbooks:realm_version:realm-42', -3);

    expect($service->realmVersion('realm-42'))->toBe(0);
});
