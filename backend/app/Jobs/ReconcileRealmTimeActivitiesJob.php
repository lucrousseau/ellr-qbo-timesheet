<?php

/**
 * Queued realm-wide time activity reconcile after OAuth connect.
 */

namespace App\Jobs;

use App\Enums\TimeActivityReconcileScope;
use App\Models\QuickBooksToken;
use App\Services\TimeActivitySyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Imports time activities for one QuickBooks company realm.
 */
class ReconcileRealmTimeActivitiesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Seconds to deduplicate identical reconcile jobs for the same realm.
     */
    public int $uniqueFor = 300;

    /**
     * @param  int  $tokenId  QuickBooks token primary key.
     * @param  bool  $fullLookback  When true, imports every configured lookback window.
     */
    public function __construct(
        public readonly int $tokenId,
        public readonly bool $fullLookback = true,
    ) {}

    /**
     * Deduplicates reconcile jobs per realm and lookback mode.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        $realmId = QuickBooksToken::query()->whereKey($this->tokenId)->value('realm_id')
            ?? (string) $this->tokenId;

        return 'quickbooks:reconcile:'.$realmId.':'.($this->fullLookback ? 'full' : 'recent');
    }

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

        $scope = $this->fullLookback ? TimeActivityReconcileScope::Full : TimeActivityReconcileScope::Recent;
        $sync->reconcileRealm($token, $scope);
    }
}
