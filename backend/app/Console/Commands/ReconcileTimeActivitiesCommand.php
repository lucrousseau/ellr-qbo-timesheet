<?php

/**
 * Artisan command to reconcile QuickBooks time activities into local snapshots.
 */

namespace App\Console\Commands;

use App\Services\TimeActivitySyncService;
use Illuminate\Console\Command;

/**
 * Imports time activities for every connected QuickBooks company realm.
 */
class ReconcileTimeActivitiesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'quickbooks:reconcile-time-activities {--scheduled : Lightweight cron mode (recent window, skip recently synced realms)}';

    /**
     * @var string
     */
    protected $description = 'Reconcile QuickBooks time activities into the local snapshot read model';

    /**
     * Runs the realm-wide reconcile import.
     *
     * @param  TimeActivitySyncService  $sync  Realm-wide reconcile service.
     * @return int
     */
    public function handle(TimeActivitySyncService $sync): int
    {
        $upserted = $sync->reconcileAllRealms(scheduled: $this->option('scheduled') === true);
        $this->info("Reconciled {$upserted} time activity snapshot(s).");

        return self::SUCCESS;
    }
}
