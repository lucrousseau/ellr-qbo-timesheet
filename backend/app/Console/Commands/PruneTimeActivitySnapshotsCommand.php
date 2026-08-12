<?php

/**
 * Artisan command to permanently remove expired soft-deleted time activity snapshots.
 */

namespace App\Console\Commands;

use App\Services\TimeActivitySnapshotPruneService;
use Illuminate\Console\Command;

/**
 * Hard-deletes soft-deleted snapshots older than the configured retention window.
 */
class PruneTimeActivitySnapshotsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'quickbooks:prune-time-activity-snapshots';

    /**
     * @var string
     */
    protected $description = 'Permanently delete soft-deleted time activity snapshots past the retention window';

    /**
     * Prunes expired snapshot rows.
     *
     * @param  TimeActivitySnapshotPruneService  $snapshots  Snapshot maintenance service.
     * @return int
     */
    public function handle(TimeActivitySnapshotPruneService $snapshots): int
    {
        $retentionDays = (int) config('quickbooks.snapshot_soft_delete_retention_days', 90); // @pest-mutate-ignore config already numeric

        if ($retentionDays <= 0) {
            $this->info('Snapshot soft-delete pruning is disabled (retention days <= 0).');

            return self::SUCCESS;
        }

        $deleted = $snapshots->pruneExpiredSoftDeletes($retentionDays);
        $this->info("Pruned {$deleted} expired soft-deleted snapshot(s).");

        return self::SUCCESS;
    }
}
