<?php

/**
 * Lists QuickBooks time activities with pagination metadata.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Support\UsesQuickBooksEmployeeScope;

/**
 * Reads time activities from the local snapshot read model.
 */
class TimeActivityListService
{
    use UsesQuickBooksEmployeeScope {
        __construct as private initializeQuickBooksEmployeeScope;
    }

    /**
     * Injects QuickBooks dependencies, sync, and snapshot queries.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QboEmployeeAuthorizationService  $employeeAuthorization  QBO employee ownership checks.
     * @param  QuickBooksApiErrorFormatterService  $apiErrors  QuickBooks API error JSON formatter.
     * @param  TimeActivitySnapshotService  $snapshots  Local snapshot persistence.
     * @param  TimeActivityReconcileCoordinatorService  $reconcileCoordinator  Reconcile dispatch and inline refresh.
     */
    public function __construct(
        QuickBooksService $quickBooks,
        QboEmployeeAuthorizationService $employeeAuthorization,
        QuickBooksApiErrorFormatterService $apiErrors,
        private readonly TimeActivitySnapshotService $snapshots,
        private readonly TimeActivityReconcileCoordinatorService $reconcileCoordinator,
    ) {
        $this->initializeQuickBooksEmployeeScope($quickBooks, $employeeAuthorization, $apiErrors);
    }

    /**
     * Lists time activities for the QBO employee linked to the user.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  int  $startPosition  Application page start (1-based).
     * @param  int  $maxResults  Maximum rows to return for this page.
     * @param  bool  $refresh  When true, reconciles from QuickBooks before reading snapshots.
     * @return array{data: array<int, mixed>, meta: array{count: int, max_results: int, start_position: int, truncated: bool}}
     */
    public function listForUser(
        User $user,
        QuickBooksToken $token,
        int $startPosition = 1,
        int $maxResults = 100,
        bool $refresh = false,
    ): array {
        $employeeRef = $this->employeeAuthorization->resolveEmployeeRef($user);
        $realmId = $token->realm_id;
        $configMax = (int) config('quickbooks.time_activities_max_results', 100); // @pest-mutate-ignore list pagination cap from config
        $maxResults = min(max(1, $maxResults), $configMax);
        $startPosition = max(1, $startPosition);

        if ($refresh || ! $this->snapshots->realmHasSnapshots($realmId)) {
            $this->reconcileCoordinator->prepareRealmForList($token, $refresh);
        }

        [$items, $total] = $this->snapshots->listApiObjectsForEmployee(
            $realmId,
            $employeeRef,
            $startPosition,
            $maxResults,
        );
        $count = count($items);
        $truncated = $this->isTruncated($count, $maxResults, $startPosition, $total);

        return [
            'data' => $items,
            'meta' => [
                'count' => $count,
                'max_results' => $maxResults,
                'start_position' => $startPosition,
                'truncated' => $truncated,
            ],
        ];
    }

    /**
     * Determines whether additional rows exist beyond the current page.
     *
     * @param  int  $count  Rows returned for the current page.
     * @param  int  $maxResults  Requested page size.
     * @param  int  $startPosition  Application page start (1-based).
     * @param  int  $total  Total snapshot rows for the employee.
     * @return bool
     */
    private function isTruncated(int $count, int $maxResults, int $startPosition, int $total): bool
    {
        if ($count < $maxResults) {
            return false;
        }

        return ($startPosition + $count - 1) < $total;
    }
}
