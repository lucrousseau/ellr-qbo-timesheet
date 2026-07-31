<?php

/**
 * Purges QuickBooks realm-scoped local data when a tenant disconnects or is deleted.
 */

namespace App\Services;

use App\Models\Organization;
use App\Models\QboRealmSyncState;
use App\Models\TimeActivitySnapshot;

/**
 * Removes realm read-model rows and cache entries that outlive tenant bindings.
 */
class OrganizationRealmDataPurgeService
{
    /**
     * @param  QboListCacheService  $listCache  Realm-scoped QuickBooks list cache.
     */
    public function __construct(
        private readonly QboListCacheService $listCache,
    ) {}

    /**
     * Deletes snapshots, sync state, and cache data for a QuickBooks company realm.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @return void
     */
    public function purgeRealm(string $realmId): void
    {
        if ($realmId === '') {
            return;
        }

        $chunkSize = max(1, (int) config('quickbooks.snapshot_purge_chunk_size', 1000));

        TimeActivitySnapshot::withTrashed()
            ->where('realm_id', $realmId)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($snapshots): void {
                foreach ($snapshots as $snapshot) {
                    $snapshot->forceDelete();
                }
            });

        QboRealmSyncState::query()
            ->where('realm_id', $realmId)
            ->delete();

        $this->listCache->forgetRealm($realmId);
    }

    /**
     * Purges realm data when no organization currently claims the QuickBooks company.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @return void
     */
    public function purgeRealmWhenUnclaimed(string $realmId): void
    {
        if ($realmId === '') {
            return;
        }

        $claimed = Organization::query()
            ->where('realm_id', $realmId)
            ->exists();

        if (! $claimed) {
            $this->purgeRealm($realmId);
        }
    }
}
