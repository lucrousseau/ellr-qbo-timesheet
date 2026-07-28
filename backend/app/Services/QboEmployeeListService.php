<?php

/**
 * Lists QuickBooks employees for administrator pickers.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Support\QboEmployeeEmail;
use App\Support\QboEmployeeQuery;
use App\Support\QboQueryResult;

/**
 * Queries QuickBooks for active employee records with realm-scoped caching.
 */
class QboEmployeeListService
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
     * Returns active employees for the connected QuickBooks company, using cache when allowed.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  bool  $refresh  When true, bypasses and replaces the cached employee list.
     * @return array<int, array{id: string, display_name: string, email: string|null}>
     */
    public function listActive(QuickBooksToken $token, bool $refresh = false): array
    {
        return $this->listCache->remember(
            QboListCacheService::RESOURCE_EMPLOYEES,
            $token->realm_id,
            $refresh,
            fn (): array => $this->queryActive($token),
        );
    }

    /**
     * Removes the cached employee list for a QuickBooks company realm.
     *
     * @param  string  $realmId  Intuit company realm identifier.
     * @return void
     */
    public function forgetCachedForRealm(string $realmId): void
    {
        $this->listCache->forget(QboListCacheService::RESOURCE_EMPLOYEES, $realmId);
    }

    /**
     * Builds the cache key for an employee list scoped to a QuickBooks realm.
     *
     * @param  string  $realmId  Intuit company realm identifier.
     * @return string
     */
    public static function cacheKeyForRealm(string $realmId): string
    {
        return app(QboListCacheService::class)->cacheKey(
            QboListCacheService::RESOURCE_EMPLOYEES,
            $realmId,
        );
    }

    /**
     * Probes QuickBooks employee read access for a newly connected token.
     *
     * @param  QuickBooksToken  $token  OAuth token to validate.
     * @return void
     */
    public function assertCanListEmployees(QuickBooksToken $token): void
    {
        $this->queryActive($token);
    }

    /**
     * Queries QuickBooks for active employees without using the cache.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @return array<int, array{id: string, display_name: string, email: string|null}>
     */
    private function queryActive(QuickBooksToken $token): array
    {
        $dataService = $this->quickBooks->dataService($token);
        $maxResults = $this->listCache->maxResults();
        $employees = $dataService->Query(QboEmployeeQuery::listActive($maxResults));

        if ($error = $dataService->getLastError()) {
            abort($this->apiErrors->jsonResponse($error));
        }

        return array_values(array_map(
            fn (object $employee): array => $this->normalizeEmployee($employee),
            QboQueryResult::entities($employees),
        ));
    }

    /**
     * Maps a QuickBooks employee entity to a JSON-friendly shape.
     *
     * @param  object  $employee  QuickBooks Employee entity.
     * @return array{id: string, display_name: string, email: string|null}
     */
    private function normalizeEmployee(object $employee): array
    {
        return [
            'id' => (string) ($employee->Id ?? ''),
            'display_name' => (string) ($employee->DisplayName ?? ''),
            'email' => QboEmployeeEmail::fromEmployee($employee),
        ];
    }
}
