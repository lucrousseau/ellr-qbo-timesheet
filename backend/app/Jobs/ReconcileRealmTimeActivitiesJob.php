<?php

/**
 * Queued realm-wide time activity reconcile after OAuth connect.
 */

namespace App\Jobs;

use App\Models\QuickBooksToken;
use App\Services\TimeActivitySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Imports time activities for one QuickBooks company realm.
 */
class ReconcileRealmTimeActivitiesJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $tokenId  QuickBooks token primary key.
     */
    public function __construct(
        public readonly int $tokenId,
    ) {}

    /**
     * Runs the realm reconcile import.
     *
     * @param  TimeActivitySyncService  $sync  Realm-wide reconcile service.
     * @return void
     */
    public function handle(TimeActivitySyncService $sync): void
    {
        $token = QuickBooksToken::query()->find($this->tokenId);

        if ($token === null) {
            return;
        }

        $sync->reconcileRealm($token);
    }
}
