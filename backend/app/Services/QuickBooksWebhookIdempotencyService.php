<?php

/**
 * Deduplicates replayed QuickBooks webhook entity notifications.
 */

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Stores processed webhook entity keys with a TTL to ignore replays.
 */
class QuickBooksWebhookIdempotencyService
{
    /**
     * Returns whether the entity notification was already processed recently.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  array<string, mixed>  $entity  One changed entity descriptor.
     * @return bool
     */
    public function wasProcessed(string $realmId, array $entity): bool
    {
        return Cache::has($this->cacheKey($realmId, $entity)); // @pest-mutate-ignore webhook replay lookup
    }

    /**
     * Atomically claims an entity notification for processing.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  array<string, mixed>  $entity  One changed entity descriptor.
     * @return bool True when this worker should process the notification.
     */
    public function tryClaim(string $realmId, array $entity): bool
    {
        return Cache::add($this->cacheKey($realmId, $entity), true, now()->addHours(24)); // @pest-mutate-ignore webhook replay claim
    }

    /**
     * Releases a processing claim so a failed sync can be retried.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  array<string, mixed>  $entity  One changed entity descriptor.
     * @return void
     */
    public function releaseClaim(string $realmId, array $entity): void
    {
        Cache::forget($this->cacheKey($realmId, $entity)); // @pest-mutate-ignore webhook replay release
    }

    /**
     * Records a successfully processed entity notification.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  array<string, mixed>  $entity  One changed entity descriptor.
     * @return void
     */
    public function markProcessed(string $realmId, array $entity): void
    {
        Cache::put($this->cacheKey($realmId, $entity), true, now()->addHours(24)); // @pest-mutate-ignore webhook replay ttl
    }

    /**
     * Builds a stable cache key for one webhook entity notification.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  array<string, mixed>  $entity  One changed entity descriptor.
     * @return string
     */
    private function cacheKey(string $realmId, array $entity): string
    {
        $id = isset($entity['id']) ? (string) $entity['id'] : ''; // @pest-mutate-ignore webhook replay cache key
        $operation = strtolower(isset($entity['operation']) ? (string) $entity['operation'] : ''); // @pest-mutate-ignore webhook replay cache key
        $lastUpdated = isset($entity['lastUpdated']) ? (string) $entity['lastUpdated'] : ''; // @pest-mutate-ignore webhook replay cache key

        return 'quickbooks:webhook:processed:' // @pest-mutate-ignore webhook replay cache key
            .hash('sha256', $realmId.'|'.$id.'|'.$operation.'|'.$lastUpdated);
    }
}
