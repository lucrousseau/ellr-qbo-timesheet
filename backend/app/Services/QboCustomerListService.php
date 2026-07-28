<?php

/**
 * Lists QuickBooks customers for timesheet pickers.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Support\QboCustomerResolver;
use App\Support\QboQueryResult;
use App\Support\TimeActivityQuery;

/**
 * Queries QuickBooks for customers linked to an employee through time activities.
 */
class QboCustomerListService
{
    /**
     * Injects QuickBooks SDK access, list caching, and API error formatting.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QboListCacheService  $listCache  Shared QBO list cache policy.
     * @param  QuickBooksApiErrorFormatterService  $apiErrors  QuickBooks API error JSON formatter.
     * @param  QboCustomerResolver  $customerResolver  Customer reference resolver.
     * @param  QboEmployeeAuthorizationService  $employeeAuthorization  QBO employee ownership checks.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QboListCacheService $listCache,
        private readonly QuickBooksApiErrorFormatterService $apiErrors,
        private readonly QboCustomerResolver $customerResolver,
        private readonly QboEmployeeAuthorizationService $employeeAuthorization,
    ) {}

    /**
     * Returns customers associated with the user's QBO employee, using cache when allowed.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  bool  $refresh  When true, bypasses and replaces the cached customer list.
     * @return array<int, array{id: string, display_name: string}>
     */
    public function listForUser(User $user, QuickBooksToken $token, bool $refresh = false): array
    {
        $employeeRef = $this->employeeAuthorization->resolveEmployeeRef($user);

        return $this->listCache->remember(
            QboListCacheService::RESOURCE_CUSTOMERS,
            $token->realm_id,
            $refresh,
            fn (): array => $this->queryForEmployee($token, $employeeRef),
            $employeeRef,
        );
    }

    /**
     * Collects customer references from an employee's QuickBooks time activities.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  string  $employeeRef  QuickBooks employee identifier.
     * @return array<int, array{id: string, display_name: string}>
     */
    private function queryForEmployee(QuickBooksToken $token, string $employeeRef): array
    {
        $dataService = $this->quickBooks->dataService($token);
        $refs = $this->collectCustomerRefsFromActivities($dataService, $employeeRef);

        return $this->customerResolver->resolveTopLevelCustomers(
            $dataService,
            $refs,
            $this->listCache->maxResults(),
        );
    }

    /**
     * Paginates time activities and extracts linked customer references.
     *
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  string  $employeeRef  QuickBooks employee identifier.
     * @return list<string>
     */
    private function collectCustomerRefsFromActivities(object $dataService, string $employeeRef): array
    {
        $pageSize = min(
            $this->listCache->maxResults(),
            max(1, (int) config('quickbooks.time_activities_max_results', 100)),
        );
        $maxPages = max(1, (int) config('quickbooks.employee_customer_scan_max_pages', 25));
        $refs = [];

        for ($page = 0; $page < $maxPages; $page++) {
            $startPosition = ($page * $pageSize) + 1;
            $activities = $dataService->Query(
                TimeActivityQuery::listForEmployee($employeeRef, $startPosition, $pageSize),
            );

            if ($error = $dataService->getLastError()) {
                abort($this->apiErrors->jsonResponse($error));
            }

            $rows = QboQueryResult::entities($activities);

            if ($rows === []) {
                break;
            }

            foreach ($rows as $activity) {
                $customerRef = $this->customerResolver->extractCustomerRef($activity);
                if ($customerRef !== null) {
                    $refs[] = $customerRef;
                }

                $projectRef = $this->customerResolver->extractProjectRef($activity);
                if ($projectRef !== null) {
                    $refs[] = $projectRef;
                }
            }

            if (count($rows) < $pageSize) {
                break;
            }
        }

        return $refs;
    }
}
