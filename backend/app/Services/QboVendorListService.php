<?php

/**
 * Lists QuickBooks vendors for expense EntityRef pickers.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Support\QboExpenseQuery;
use App\Support\QboQueryResult;

/**
 * Queries QuickBooks for active vendor records with realm-scoped caching.
 */
class QboVendorListService
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
     * Returns active vendors for the connected QuickBooks company.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  bool  $refresh  When true, bypasses and replaces the cached vendor list.
     * @return array<int, array{id: string, display_name: string}>
     */
    public function listActive(QuickBooksToken $token, bool $refresh = false): array
    {
        return $this->listCache->remember(
            QboListCacheService::RESOURCE_VENDORS,
            $token->realm_id,
            $refresh,
            fn (): array => $this->queryActive($token),
        );
    }

    /**
     * Queries QuickBooks for active vendors without using the cache.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @return array<int, array{id: string, display_name: string}>
     */
    private function queryActive(QuickBooksToken $token): array
    {
        $dataService = $this->quickBooks->dataService($token);
        $maxResults = $this->listCache->maxResults();
        $vendors = $dataService->Query(QboExpenseQuery::listVendors($maxResults));

        if ($error = $dataService->getLastError()) {
            abort($this->apiErrors->jsonResponse($error));
        }

        return array_values(array_map(
            fn (object $vendor): array => [
                'id' => (string) ($vendor->Id ?? ''),
                'display_name' => (string) ($vendor->DisplayName ?? ''),
            ],
            QboQueryResult::entities($vendors),
        ));
    }
}
