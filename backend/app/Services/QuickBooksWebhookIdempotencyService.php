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
        return Cache::has($this->cacheKey($realmId, $entity));
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
        Cache::put($this->cacheKey($realmId, $entity), true, now()->addHours(24));
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
        $id = isset($entity['id']) ? (string) $entity['id'] : '';
        $operation = strtolower(isset($entity['operation']) ? (string) $entity['operation'] : '');
        $lastUpdated = isset($entity['lastUpdated']) ? (string) $entity['lastUpdated'] : '';

        return 'quickbooks:webhook:processed:'
            .hash('sha256', $realmId.'|'.$id.'|'.$operation.'|'.$lastUpdated);
    }
}
