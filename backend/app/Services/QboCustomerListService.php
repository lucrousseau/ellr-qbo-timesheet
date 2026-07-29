<?php

/**
 * Lists QuickBooks customers for timesheet pickers.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Support\QboCustomerQuery;
use App\Support\QboQueryResult;

/**
 * Queries QuickBooks customers for admin pickers and employee-scoped timesheet access.
 */
class QboCustomerListService
{
    /**
     * Injects QuickBooks SDK access, list caching, and API error formatting.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QboListCacheService  $listCache  Shared QBO list cache policy.
     * @param  QuickBooksApiErrorFormatterService  $apiErrors  QuickBooks API error JSON formatter.
     * @param  QboEmployeeAuthorizationService  $employeeAuthorization  QBO employee ownership checks.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QboListCacheService $listCache,
        private readonly QuickBooksApiErrorFormatterService $apiErrors,
        private readonly QboEmployeeAuthorizationService $employeeAuthorization,
    ) {}

    /**
     * Returns customers available to the signed-in timesheet user.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  bool  $refresh  When true, bypasses and replaces the cached all-customers list.
     * @return array<int, array{id: string, display_name: string}>
     */
    public function listForUser(User $user, QuickBooksToken $token, bool $refresh = false): array
    {
        $this->employeeAuthorization->resolveEmployeeRef($user);

        if ($user->qbo_all_customers_access) {
            return $this->listAllActive($token, $refresh);
        }

        return $user->assignedQboCustomerPickerRows();
    }

    /**
     * Lists all active top-level customers for administrator assignment pickers.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  bool  $refresh  When true, bypasses and replaces the cached customer list.
     * @return array<int, array{id: string, display_name: string}>
     */
    public function listAllActive(QuickBooksToken $token, bool $refresh = false): array
    {
        return $this->listCache->remember(
            QboListCacheService::RESOURCE_ALL_CUSTOMERS,
            $token->realm_id,
            $refresh,
            fn (): array => $this->queryAllActive($token),
        );
    }

    /**
     * Queries QuickBooks for all active top-level customers.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @return array<int, array{id: string, display_name: string}>
     */
    private function queryAllActive(QuickBooksToken $token): array
    {
        $dataService = $this->quickBooks->dataService($token);
        $maxResults = $this->listCache->maxResults();
        $result = $dataService->Query(QboCustomerQuery::listCustomers($maxResults));

        if ($error = $dataService->getLastError()) {
            abort($this->apiErrors->jsonResponse($error));
        }

        $rows = [];

        foreach (QboQueryResult::entities($result) as $customer) {
            $rows[] = [
                'id' => (string) ($customer->Id ?? ''),
                'display_name' => (string) ($customer->DisplayName ?? ''),
            ];
        }

        usort(
            $rows,
            fn (array $left, array $right): int => strcasecmp($left['display_name'], $right['display_name']),
        );

        return $rows;
    }
}
