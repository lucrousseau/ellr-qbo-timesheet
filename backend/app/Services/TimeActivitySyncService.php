<?php

/**
 * Reconciles QuickBooks time activities into the local read model.
 */

namespace App\Services;

use App\Exceptions\QuickBooksException;
use App\Models\QboRealmSyncState;
use App\Models\QuickBooksToken;
use App\Support\TimeActivityQuery;
use Illuminate\Support\Carbon;

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
     * @return int Number of rows upserted.
     */
    public function reconcileRealm(QuickBooksToken $token): int
    {
        $token->loadMissing('user.organization');
        $organization = $token->user?->organization;

        if ($organization !== null) {
            $this->organizationTimezone->syncIfMissing($organization, $token);
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

        foreach ($this->lookbackSteps() as $index => $lookbackDays) {
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
     * @return int Total rows upserted.
     */
    public function reconcileAllRealms(): int
    {
        $total = 0;
        $realmIds = QuickBooksToken::query()
            ->select('realm_id')
            ->distinct()
            ->pluck('realm_id');

        foreach ($realmIds as $realmId) {
            $token = QuickBooksToken::query()
                ->where('realm_id', $realmId)
                ->latest('id')
                ->first();

            if ($token === null) {
                continue;
            }

            $total += $this->reconcileRealm($token);
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

            foreach ($activities as $activity) {
                if (! is_object($activity)) {
                    continue;
                }

                try {
                    $snapshot = $this->snapshots->upsertFromQboEntity($realmId, $dataService, $activity);
                    $seenQboIds[] = $snapshot->qbo_id;
                    $upserted++;
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }

            if (count($activities) < $batchSize) {
                return [$upserted, $seenQboIds, true];
            }

            $qboStart += $batchSize;
        }

        return [$upserted, $seenQboIds, false];
    }

    /**
     * @return list<int>
     */
    private function lookbackSteps(): array
    {
        $configured = config('quickbooks.time_activities_lookback_steps');
        $steps = is_array($configured) ? $configured : [14, 30, 90]; // @pest-mutate-ignore default lookback ladder when config is missing
        $maxLookback = (int) config('quickbooks.time_activities_lookback_days', 90); // @pest-mutate-ignore
        $normalized = [];

        foreach ($steps as $step) {
            $days = (int) $step;

            if ($days > 0 && $days <= $maxLookback) {
                $normalized[$days] = $days;
            }
        }

        if ($normalized === []) {
            $normalized[$maxLookback] = $maxLookback;
        }

        $values = array_values($normalized);
        sort($values);

        return $values;
    }
}
