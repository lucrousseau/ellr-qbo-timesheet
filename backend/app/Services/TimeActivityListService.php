<?php

/**
 * Lists QuickBooks time activities with pagination metadata.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Support\TimeActivityQuery;
use App\Support\UsesQuickBooksEmployeeScope;

/**
 * Queries QuickBooks for time activities scoped to the user's employee.
 */
class TimeActivityListService
{
    use UsesQuickBooksEmployeeScope;

    /**
     * Lists time activities for the QBO employee linked to the user.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  int  $startPosition  QuickBooks STARTPOSITION (1-based).
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap for this request.
     * @return array{data: array<int, mixed>, meta: array{count: int, max_results: int, start_position: int, truncated: bool}}
     */
    public function listForUser(
        User $user,
        QuickBooksToken $token,
        int $startPosition = 1,
        int $maxResults = 100,
    ): array {
        $employeeRef = $this->employeeAuthorization->resolveEmployeeRef($user);
        $dataService = $this->quickBooks->dataService($token);
        $configMax = (int) config('quickbooks.time_activities_max_results', 100);
        $maxResults = min(max(1, $maxResults), $configMax);
        $startPosition = max(1, $startPosition);

        $items = $this->queryActivities($dataService, $employeeRef, $startPosition, $maxResults);
        $count = count($items);
        $truncated = $this->isTruncated($dataService, $employeeRef, $count, $maxResults, $startPosition);

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
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  string  $employeeRef  QuickBooks employee reference.
     * @param  int  $count  Number of rows returned for the current page.
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap.
     * @param  int  $startPosition  QuickBooks STARTPOSITION (1-based).
     * @return bool
     */
    private function isTruncated(
        object $dataService,
        string $employeeRef,
        int $count,
        int $maxResults,
        int $startPosition,
    ): bool {
        if ($count < $maxResults) {
            return false;
        }

        if (! config('quickbooks.time_activities_probe_truncated', true)) {
            return true;
        }

        return $this->hasMoreActivities($dataService, $employeeRef, $startPosition + $maxResults);
    }

    /**
     * Runs a paginated QuickBooks query and returns activity rows.
     *
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  string  $employeeRef  QuickBooks employee reference.
     * @param  int  $startPosition  QuickBooks STARTPOSITION (1-based).
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap.
     * @return array<int, mixed>
     */
    private function queryActivities(
        object $dataService,
        string $employeeRef,
        int $startPosition,
        int $maxResults,
    ): array {
        $activities = $dataService->Query(
            TimeActivityQuery::listForEmployee($employeeRef, $startPosition, $maxResults),
        );

        if ($error = $dataService->getLastError()) {
            abort($this->apiErrors->jsonResponse($error));
        }

        return is_array($activities) ? $activities : [];
    }

    /**
     * Probes QuickBooks for an additional row beyond the current page.
     *
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  string  $employeeRef  QuickBooks employee reference.
     * @param  int  $startPosition  QuickBooks STARTPOSITION for the probe row.
     * @return bool
     */
    private function hasMoreActivities(object $dataService, string $employeeRef, int $startPosition): bool
    {
        $probe = $dataService->Query(
            TimeActivityQuery::listForEmployee($employeeRef, $startPosition, 1),
        );

        if ($error = $dataService->getLastError()) {
            abort($this->apiErrors->jsonResponse($error));
        }

        return is_array($probe) && count($probe) > 0;
    }
}
