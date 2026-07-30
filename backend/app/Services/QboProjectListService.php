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
        $parentRef = $this->normalizeRef($customerRef);

        if ($parentRef === '') {
            return [];
        }

        $allJobs = $this->listCache->remember(
            QboListCacheService::RESOURCE_PROJECTS,
            $token->realm_id,
            $refresh,
            fn (): array => $this->queryAllActiveJobs($token),
        );

        return array_values(array_map(
            fn (array $project): array => [
                'id' => $project['id'],
                'display_name' => $project['display_name'],
            ],
            array_filter(
                $allJobs,
                fn (array $project): bool => $project['parent_ref'] === $parentRef,
            ),
        ));
    }

    /**
     * Queries QuickBooks for all active job customers without using the cache.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @return array<int, array{id: string, display_name: string, parent_ref: string}>
     */
    private function queryAllActiveJobs(QuickBooksToken $token): array
    {
        $dataService = $this->quickBooks->dataService($token);
        $pageSize = $this->listCache->maxResults();
        $maxPages = (int) config('quickbooks.list_scan_max_pages', 10);
        $startPosition = 1;
        $allJobs = [];

        for ($page = 0; $page < $maxPages; $page++) {
            $projects = $dataService->Query(
                QboCustomerQuery::listActiveJobCustomers($pageSize, $startPosition),
            );

            if ($error = $dataService->getLastError()) {
                abort($this->apiErrors->jsonResponse($error));
            }

            $entities = QboQueryResult::entities($projects);

            foreach ($entities as $project) {
                $allJobs[] = [
                    'id' => (string) ($project->Id ?? ''),
                    'display_name' => (string) ($project->DisplayName ?? ''),
                    'parent_ref' => $this->extractParentRef($project),
                ];
            }

            if (count($entities) < $pageSize) {
                break;
            }

            $startPosition += $pageSize;
        }

        return $allJobs;
    }

    /**
     * Reads the parent customer reference from a job customer entity.
     *
     * @param  object  $project  QuickBooks Customer entity.
     * @return string
     */
    private function extractParentRef(object $project): string
    {
        $parentRef = $project->ParentRef ?? null;

        if (is_object($parentRef) && isset($parentRef->value)) {
            return $this->normalizeRef((string) $parentRef->value);
        }

        if (is_string($parentRef) || is_int($parentRef)) {
            return $this->normalizeRef((string) $parentRef);
        }

        return '';
    }

    /**
     * Restricts a QuickBooks entity reference to safe numeric characters.
     *
     * @param  string  $ref  Raw reference value from the client or QuickBooks.
     * @return string
     */
    private function normalizeRef(string $ref): string
    {
        return preg_replace('/\D+/', '', $ref) ?? '';
    }
}
