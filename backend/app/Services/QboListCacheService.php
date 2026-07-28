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
     * @return string
     */
    public function cacheKey(string $resource, string $realmId): string
    {
        return "quickbooks:{$resource}:{$realmId}";
    }

    /**
     * Returns a cached list or fetches it from QuickBooks when needed.
     *
     * @param  string  $resource  List resource name.
     * @param  string  $realmId  Intuit company realm identifier.
     * @param  bool  $refresh  When true, bypasses and replaces the cached list.
     * @param  callable(): array  $fetch  Callback that queries QuickBooks.
     * @return array
     */
    public function remember(string $resource, string $realmId, bool $refresh, callable $fetch): array
    {
        $cacheKey = $this->cacheKey($resource, $realmId);
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
     * @return void
     */
    public function forget(string $resource, string $realmId): void
    {
        Cache::forget($this->cacheKey($resource, $realmId));
    }

    /**
     * Removes all cached QBO list data for a QuickBooks company realm.
     *
     * @param  string  $realmId  Intuit company realm identifier.
     * @return void
     */
    public function forgetRealm(string $realmId): void
    {
        foreach ($this->resources() as $resource) {
            $this->forget($resource, $realmId);
        }
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
        ];
    }
}
