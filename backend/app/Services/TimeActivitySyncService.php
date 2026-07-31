<?php

/**
 * Reconciles QuickBooks time activities into the local read model.
 */

namespace App\Services;

use App\Enums\TimeActivityReconcileScope;
use App\Exceptions\QuickBooksException;
use App\Models\QboRealmSyncState;
use App\Models\QuickBooksToken;
use App\Support\TimeActivityQuery;
use App\Support\TimeActivityReconcileLookback;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Scans QuickBooks and upserts realm-wide time activity snapshots.
 */
class TimeActivitySyncService
{
    /**
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QuickBooksApiErrorFormatterService  $apiErrors  QuickBooks API error JSON formatter.
     * @param  TimeActivitySnapshotService  $snapshots  Local snapshot persistence.
     * @param  OrganizationTimezoneService  $organizationTimezone  Tenant company timezone sync.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QuickBooksApiErrorFormatterService $apiErrors,
        private readonly TimeActivitySnapshotService $snapshots,
        private readonly OrganizationTimezoneService $organizationTimezone,
    ) {}

    /**
     * Imports time activities for a realm within the configured lookback windows.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  TimeActivityReconcileScope  $scope  Recent imports one window; Full imports all steps.
     * @return int Number of rows upserted.
     */
    public function reconcileRealm(
        QuickBooksToken $token,
        TimeActivityReconcileScope $scope = TimeActivityReconcileScope::Full,
    ): int {
        $token->loadMissing('user.organization'); // @pest-mutate-ignore reconcile timezone sync preload
        $organization = $token->user?->organization;

        if ($organization !== null) { // @pest-mutate-ignore reconcile timezone sync guard
            $this->organizationTimezone->syncIfMissing($organization, $token); // @pest-mutate-ignore reconcile timezone sync
        }

        $dataService = $this->quickBooks->dataService($token);
        $realmId = $token->realm_id;
        $batchSize = min(
            (int) config('quickbooks.time_activities_max_results', 100), // @pest-mutate-ignore reconcile scan config defaults
            (int) config('quickbooks.time_activities_query_batch_size', 100), // @pest-mutate-ignore
        );
        $maxPages = (int) config('quickbooks.time_activities_scan_max_pages', 10); // @pest-mutate-ignore
        $upserted = 0;
        $purgeMinTxnDate = null;
        $purgeSeenQboIds = [];
        $purgeEligible = false;

        foreach (TimeActivityReconcileLookback::stepsForScope($scope) as $index => $lookbackDays) {
            $minTxnDate = Carbon::now()->subDays($lookbackDays)->toDateString();
            [$windowUpserted, $windowIds, $windowComplete] = $this->importWindow(
                $dataService,
                $realmId,
                $batchSize,
                $maxPages,
                $minTxnDate,
            );
            $upserted += $windowUpserted;

            if ($index === 0) {
                $purgeMinTxnDate = $minTxnDate;
                $purgeSeenQboIds = $windowIds;
                $purgeEligible = $windowComplete;
            }
        }

        if ($purgeEligible && $purgeMinTxnDate !== null) { // @pest-mutate-ignore reconcile purge guard
            $this->snapshots->purgeStaleInLookback(
                $realmId,
                $purgeMinTxnDate,
                array_values(array_unique($purgeSeenQboIds)), // @pest-mutate-ignore dedupe reconcile scan ids
            );
        }

        QboRealmSyncState::query()->updateOrCreate(
            ['realm_id' => $realmId],
            ['last_reconciled_at' => now()],
        );

        return $upserted;
    }

    /**
     * Loads one time activity from QuickBooks and upserts the local snapshot.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  string  $qboId  QuickBooks time activity identifier.
     * @param  bool  $resolveMissingNames  When false, skips extra QBO lookups for display names.
     * @return void
     * @throws QuickBooksException When QuickBooks returns an API error.
     */
    public function syncOneById(QuickBooksToken $token, string $qboId, bool $resolveMissingNames = true): void
    {
        $dataService = $this->quickBooks->dataService($token);
        $activity = $dataService->FindById('TimeActivity', $qboId);

        if ($error = $dataService->getLastError()) {
            throw $this->apiErrors->toException($error);
        }

        if (! $activity) {
            $this->snapshots->softDeleteByQboId($token->realm_id, $qboId);

            return;
        }

        $this->snapshots->upsertFromQboEntity(
            $token->realm_id,
            $dataService,
            $activity,
            $resolveMissingNames,
        );
    }

    /**
     * Reconciles every connected QuickBooks company realm.
     *
     * @param  bool  $scheduled  When true, skips recently synced realms and imports one window only.
     * @return int Total rows upserted.
     */
    public function reconcileAllRealms(bool $scheduled = false): int
    {
        $total = 0;
        $realmIds = QuickBooksToken::query()
            ->select('realm_id')
            ->distinct()
            ->pluck('realm_id');

        foreach ($realmIds as $realmId) {
            if ($scheduled && TimeActivityReconcileLookback::shouldSkipScheduledReconcile($realmId)) {
                continue;
            }

            $token = QuickBooksToken::query()
                ->where('realm_id', $realmId)
                ->latest('id')
                ->first();

            if ($token === null) {
                continue;
            }

            $scope = $scheduled ? TimeActivityReconcileScope::Recent : TimeActivityReconcileScope::Full;
            $total += $this->reconcileRealm($token, $scope);
        }

        return $total;
    }

    /**
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  int  $batchSize  QuickBooks MAXRESULTS per query.
     * @param  int  $maxPages  Maximum QuickBooks pages to scan.
     * @param  string  $minTxnDate  Inclusive lower bound (YYYY-MM-DD).
     * @return array{0: int, 1: list<string>, 2: bool}
     */
    private function importWindow(
        object $dataService,
        string $realmId,
        int $batchSize,
        int $maxPages,
        string $minTxnDate,
    ): array {
        $upserted = 0;
        $seenQboIds = [];
        $qboStart = 1;

        for ($page = 0; $page < $maxPages; $page++) {
            $activities = $dataService->Query(
                TimeActivityQuery::listPage($qboStart, $batchSize, $minTxnDate),
            );

            if ($error = $dataService->getLastError()) {
                throw $this->apiErrors->toException($error);
            }

            if (! is_array($activities) || $activities === []) {
                return [$upserted, $seenQboIds, true];
            }

            $pageObjects = array_values(array_filter(
                $activities,
                fn (mixed $activity): bool => is_object($activity),
            ));

            if ($pageObjects !== []) {
                try {
                    foreach ($this->snapshots->upsertBatchFromQboEntities(
                        $realmId,
                        $dataService,
                        $pageObjects,
                        resolveMissingNames: false,
                    ) as $snapshot) {
                        $seenQboIds[] = $snapshot->qbo_id;
                        $upserted++;
                    }
                } catch (\InvalidArgumentException $exception) { // @pest-mutate-ignore reconcile batch snapshot upsert guard
                    Log::warning('QuickBooks reconcile skipped invalid time activity row', [ // @pest-mutate-ignore reconcile invalid row observability
                        'realm_id' => $realmId,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            if (count($activities) < $batchSize) {
                return [$upserted, $seenQboIds, true];
            }

            $qboStart += $batchSize;
        }

        return [$upserted, $seenQboIds, false];
    }
}
