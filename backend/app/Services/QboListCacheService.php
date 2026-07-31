<?php

/**
 * Realm-scoped caching for read-heavy QuickBooks list endpoints.
 */

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Shared cache policy for QBO picker lists (employees, customers, projects, services).
 */
class QboListCacheService
{
    public const RESOURCE_EMPLOYEES = 'employees';

    public const RESOURCE_CUSTOMERS = 'customers';

    public const RESOURCE_ALL_CUSTOMERS = 'customers_all';

    public const RESOURCE_PROJECTS = 'projects';

    public const RESOURCE_SERVICES = 'services';

    /**
     * Returns the configured cache TTL for QBO list endpoints in minutes.
     *
     * @return int
     */
    public function ttlMinutes(): int
    {
        return max(0, (int) config('quickbooks.list_cache_ttl_minutes', 15));
    }

    /**
     * Returns the configured MAXRESULTS cap for QBO list endpoints.
     *
     * @return int
     */
    public function maxResults(): int
    {
        return max(1, (int) config('quickbooks.list_max_results', 1000));
    }

    /**
     * Builds a realm-scoped cache key for a QBO list resource.
     *
     * @param  string  $resource  List resource name (for example `employees`).
     * @param  string  $realmId  Intuit company realm identifier.
     * @param  string|null  $scope  Optional scope suffix (for example a parent customer ref).
     * @return string
     */
    public function cacheKey(string $resource, string $realmId, ?string $scope = null): string
    {
        $version = $this->realmVersion($realmId);
        $key = "quickbooks:v{$version}:{$resource}:{$realmId}";

        if ($scope !== null && $scope !== '') {
            $key .= ':'.preg_replace('/\D+/', '', $scope);
        }

        return $key;
    }

    /**
     * Returns a cached list or fetches it from QuickBooks when needed.
     *
     * @param  string  $resource  List resource name.
     * @param  string  $realmId  Intuit company realm identifier.
     * @param  bool  $refresh  When true, bypasses and replaces the cached list.
     * @param  callable(): array  $fetch  Callback that queries QuickBooks.
     * @param  string|null  $scope  Optional scope suffix for scoped lists (for example projects per customer).
     * @return array
     */
    public function remember(string $resource, string $realmId, bool $refresh, callable $fetch, ?string $scope = null): array
    {
        $cacheKey = $this->cacheKey($resource, $realmId, $scope);
        $ttlMinutes = $this->ttlMinutes();

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        if ($ttlMinutes <= 0) {
            return $fetch();
        }

        if (! $refresh) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $data = $fetch();

        if ($data !== []) {
            Cache::put($cacheKey, $data, now()->addMinutes($ttlMinutes));
        }

        return $data;
    }

    /**
     * Removes one cached list for a QuickBooks company realm.
     *
     * @param  string  $resource  List resource name.
     * @param  string  $realmId  Intuit company realm identifier.
     * @param  string|null  $scope  Optional scope suffix.
     * @return void
     */
    public function forget(string $resource, string $realmId, ?string $scope = null): void
    {
        Cache::forget($this->cacheKey($resource, $realmId, $scope));
    }

    /**
     * Removes all cached QBO list data for a QuickBooks company realm.
     *
     * @param  string  $realmId  Intuit company realm identifier.
     * @return void
     */
    public function forgetRealm(string $realmId): void
    {
        $versionKey = $this->realmVersionKey($realmId);
        $version = (int) Cache::get($versionKey, 0);
        Cache::forever($versionKey, $version + 1);

        foreach ($this->resources() as $resource) {
            $this->forget($resource, $realmId);
        }
    }

    /**
     * Returns the active cache generation for a QuickBooks company realm.
     *
     * @param  string  $realmId  Intuit company realm identifier.
     * @return int
     */
    public function realmVersion(string $realmId): int
    {
        return max(0, (int) Cache::get($this->realmVersionKey($realmId), 0));
    }

    /**
     * Builds the cache key that stores a realm list-cache generation counter.
     *
     * @param  string  $realmId  Intuit company realm identifier.
     * @return string
     */
    private function realmVersionKey(string $realmId): string
    {
        return "quickbooks:realm_version:{$realmId}";
    }

    /**
     * Lists QBO resources that use realm-scoped list caching.
     *
     * @return list<string>
     */
    private function resources(): array
    {
        return [
            self::RESOURCE_EMPLOYEES,
            self::RESOURCE_CUSTOMERS,
            self::RESOURCE_ALL_CUSTOMERS,
            self::RESOURCE_PROJECTS,
            self::RESOURCE_SERVICES,
        ];
    }
}
