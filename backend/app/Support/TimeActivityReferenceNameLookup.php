<?php

/**
 * Loads QuickBooks customer and item display names for time activity enrichment.
 */

namespace App\Support;

use App\Services\QuickBooksApiErrorFormatterService;

/**
 * Batches QuickBooks queries to resolve reference identifiers to display names.
 */
final class TimeActivityReferenceNameLookup
{
    /**
     * @param  QuickBooksApiErrorFormatterService  $apiErrors  QuickBooks API error JSON formatter.
     */
    public function __construct(
        private readonly QuickBooksApiErrorFormatterService $apiErrors,
    ) {}

    /**
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  list<string>  $customerRefs  QuickBooks customer or project identifiers.
     * @return array<string, string>
     */
    public function customerNames(object $dataService, array $customerRefs): array
    {
        return $this->loadNames(
            $dataService,
            $customerRefs,
            fn (array $chunk, int $maxResults): string => QboCustomerQuery::customersByIds($chunk, $maxResults),
            fn (object $entity): ?string => $this->nonEmpty((string) ($entity->DisplayName ?? '')),
        );
    }

    /**
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  list<string>  $itemRefs  QuickBooks item identifiers.
     * @return array<string, string>
     */
    public function itemNames(object $dataService, array $itemRefs): array
    {
        return $this->loadNames(
            $dataService,
            $itemRefs,
            fn (array $chunk, int $maxResults): string => QboCustomerQuery::itemsByIds($chunk, $maxResults),
            fn (object $entity): ?string => $this->nonEmpty((string) ($entity->Name ?? '')),
        );
    }

    /**
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  list<string>  $refs  QuickBooks identifiers.
     * @param  callable(array<int, string>, int): string  $queryBuilder  Builds a QBO SQL query.
     * @param  callable(object): (?string)  $nameExtractor  Extracts a display name from one entity.
     * @return array<string, string>
     */
    private function loadNames(
        object $dataService,
        array $refs,
        callable $queryBuilder,
        callable $nameExtractor,
    ): array {
        $normalized = $this->uniqueRefs($refs);

        if ($normalized === []) {
            return [];
        }

        $maxResults = max(1, (int) config('quickbooks.list_max_results', 1000));
        $names = [];

        foreach (array_chunk($normalized, 100) as $chunk) {
            $result = $dataService->Query($queryBuilder($chunk, $maxResults));

            if ($error = $dataService->getLastError()) {
                abort($this->apiErrors->jsonResponse($error));
            }

            foreach (QboQueryResult::entities($result) as $entity) {
                $id = (string) ($entity->Id ?? '');

                if ($id === '') {
                    continue;
                }

                $name = $nameExtractor($entity);

                if ($name !== null) {
                    $names[$id] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $refs  Raw QuickBooks identifiers.
     * @return list<string>
     */
    private function uniqueRefs(array $refs): array
    {
        $normalized = [];

        foreach ($refs as $ref) {
            $value = preg_replace('/\D+/', '', (string) $ref) ?? '';

            if ($value !== '') {
                $normalized[$value] = $value;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param  string  $value  Candidate display name.
     * @return string|null
     */
    private function nonEmpty(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
