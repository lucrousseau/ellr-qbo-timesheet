<?php

/**
 * Maintenance operations for the time activity snapshot read model.
 */

namespace App\Services;

use App\Models\TimeActivitySnapshot;

/**
 * Prunes expired soft-deleted snapshot rows in bounded chunks.
 */
class TimeActivitySnapshotPruneService
{
    /**
     * Permanently deletes soft-deleted snapshots older than the retention window.
     *
     * @param  int  $retentionDays  Minimum age in days before hard deletion.
     * @return int Number of rows permanently deleted.
     */
    public function pruneExpiredSoftDeletes(int $retentionDays): int
    {
        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($retentionDays);
        $chunkSize = max(1, (int) config('quickbooks.snapshot_purge_chunk_size', 1000)); // @pest-mutate-ignore chunk size floor from config

        $deleted = 0;

        TimeActivitySnapshot::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($snapshots) use (&$deleted): void {
                foreach ($snapshots as $snapshot) {
                    $snapshot->forceDelete();
                    $deleted++;
                }
            });

        return $deleted;
    }
}
