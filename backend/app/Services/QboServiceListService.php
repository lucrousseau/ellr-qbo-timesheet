<?php

/**
 * Lists QuickBooks service items for timesheet pickers.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Support\QboCustomerQuery;
use App\Support\QboQueryResult;

/**
 * Queries QuickBooks for active service item records with realm-scoped caching.
 */
class QboServiceListService
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
     * Returns active service items for the connected QuickBooks company, using cache when allowed.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  bool  $refresh  When true, bypasses and replaces the cached service list.
     * @return array<int, array{id: string, display_name: string}>
     */
    public function listActive(QuickBooksToken $token, bool $refresh = false): array
    {
        return $this->listCache->remember(
            QboListCacheService::RESOURCE_SERVICES,
            $token->realm_id,
            $refresh,
            fn (): array => $this->queryActive($token),
        );
    }

    /**
     * Queries QuickBooks for active service items without using the cache.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @return array<int, array{id: string, display_name: string}>
     */
    private function queryActive(QuickBooksToken $token): array
    {
        $dataService = $this->quickBooks->dataService($token);
        $maxResults = $this->listCache->maxResults();
        $items = $dataService->Query(QboCustomerQuery::listServices($maxResults));

        if ($error = $dataService->getLastError()) {
            abort($this->apiErrors->jsonResponse($error));
        }

        return array_values(array_map(
            fn (object $item): array => [
                'id' => (string) ($item->Id ?? ''),
                'display_name' => (string) ($item->Name ?? ''),
            ],
            QboQueryResult::entities($items),
        ));
    }
}
