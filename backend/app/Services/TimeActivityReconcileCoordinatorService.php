<?php

/**
 * Orchestrates when time activity reconcile runs inline versus in the queue.
 */

namespace App\Services;

use App\Enums\TimeActivityReconcileScope;
use App\Jobs\ReconcileRealmTimeActivitiesJob;
use App\Models\QuickBooksToken;

/**
 * Dispatches background backfills and runs lightweight inline refreshes.
 */
class TimeActivityReconcileCoordinatorService
{
    /**
     * @param  TimeActivitySnapshotService  $snapshots  Local snapshot persistence.
     * @param  TimeActivitySyncService  $sync  Realm-wide reconcile from QuickBooks.
     */
    public function __construct(
        private readonly TimeActivitySnapshotService $snapshots,
        private readonly TimeActivitySyncService $sync,
    ) {}

    /**
     * Ensures snapshot data is available before a list read.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  bool  $refresh  When true, reconciles every lookback window inline.
     * @return void
     */
    public function prepareRealmForList(QuickBooksToken $token, bool $refresh): void
    {
        if ($refresh) {
            $this->sync->reconcileRealm($token, TimeActivityReconcileScope::Full);

            return;
        }

        if (! $this->snapshots->realmHasSnapshots($token->realm_id)) {
            ReconcileRealmTimeActivitiesJob::dispatch($token->id, fullLookback: true);
        }
    }
}
