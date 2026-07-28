<?php

/**
 * Lists QuickBooks projects (job customers) for timesheet pickers.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Support\QboCustomerQuery;
use App\Support\QboQueryResult;

/**
 * Queries QuickBooks for active project records scoped to a parent customer.
 */
class QboProjectListService
{
    /**
     * Injects QuickBooks SDK access, list caching, and API error formatting.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QboListCacheService  $listCache  Shared QBO list cache policy.
     * @param  QuickBooksApiErrorFormatterService  $apiErrors  QuickBooks API error JSON formatter.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QboListCacheService $listCache,
        private readonly QuickBooksApiErrorFormatterService $apiErrors,
    ) {}

    /**
     * Returns active projects for a parent customer, using cache when allowed.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  string  $customerRef  Parent customer QuickBooks identifier.
     * @param  bool  $refresh  When true, bypasses and replaces the cached project list.
     * @return array<int, array{id: string, display_name: string}>
     */
    public function listForCustomer(QuickBooksToken $token, string $customerRef, bool $refresh = false): array
    {
        return $this->listCache->remember(
            QboListCacheService::RESOURCE_PROJECTS,
            $token->realm_id,
            $refresh,
            fn (): array => $this->queryForCustomer($token, $customerRef),
            $customerRef,
        );
    }

    /**
     * Queries QuickBooks for active projects without using the cache.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  string  $customerRef  Parent customer QuickBooks identifier.
     * @return array<int, array{id: string, display_name: string}>
     */
    private function queryForCustomer(QuickBooksToken $token, string $customerRef): array
    {
        $dataService = $this->quickBooks->dataService($token);
        $maxResults = $this->listCache->maxResults();
        $projects = $dataService->Query(
            QboCustomerQuery::listProjectsForCustomer($customerRef, $maxResults),
        );

        if ($error = $dataService->getLastError()) {
            abort($this->apiErrors->jsonResponse($error));
        }

        return array_values(array_map(
            fn (object $project): array => [
                'id' => (string) ($project->Id ?? ''),
                'display_name' => (string) ($project->DisplayName ?? ''),
            ],
            QboQueryResult::entities($projects),
        ));
    }
}
