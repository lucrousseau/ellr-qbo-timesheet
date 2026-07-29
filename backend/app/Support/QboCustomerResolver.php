<?php

/**
 * Resolves QuickBooks customer references to top-level customer picker rows.
 */

namespace App\Support;

use App\Services\QuickBooksApiErrorFormatterService;

/**
 * Maps job customers and activity references to active parent customers.
 */
class QboCustomerResolver
{
    /**
     * Injects QuickBooks API error formatting for failed customer queries.
     *
     * @param  QuickBooksApiErrorFormatterService  $apiErrors  QuickBooks API error JSON formatter.
     */
    public function __construct(
        private readonly QuickBooksApiErrorFormatterService $apiErrors,
    ) {}

    /**
     * Resolves customer references to unique active top-level customers.
     *
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  list<string>  $customerRefs  QuickBooks customer or job identifiers.
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap per query.
     * @return array<int, array{id: string, display_name: string}>
     */
    public function resolveTopLevelCustomers(object $dataService, array $customerRefs, int $maxResults): array
    {
        $refs = $this->uniqueRefs($customerRefs);

        if ($refs === []) {
            return [];
        }

        $entities = $this->fetchCustomersByIds($dataService, $refs, $maxResults);
        $customers = [];
        $parentRefs = [];

        foreach ($entities as $entity) {
            if (! $this->isActive($entity)) {
                continue;
            }

            if ($this->isJobCustomer($entity)) {
                $parentRef = $this->extractParentRef($entity);
                if ($parentRef !== null) {
                    $parentRefs[] = $parentRef;
                }

                continue;
            }

            $customers[(string) ($entity->Id ?? '')] = $this->normalizeCustomer($entity); // @pest-mutate-ignore normalize optional QBO customer id
        }

        foreach ($this->fetchCustomersByIds($dataService, $parentRefs, $maxResults) as $parent) {
            if (! $this->isActive($parent) || $this->isJobCustomer($parent)) {
                continue;
            }

            $customers[(string) ($parent->Id ?? '')] = $this->normalizeCustomer($parent); // @pest-mutate-ignore normalize optional QBO customer id
        }

        $rows = array_values($customers);
        usort(
            $rows,
            fn (array $left, array $right): int => strcasecmp($left['display_name'], $right['display_name']),
        );

        return $rows;
    }

    /**
     * Extracts a customer reference from a QuickBooks time activity row.
     *
     * @param  object  $activity  QuickBooks TimeActivity entity.
     * @return string|null
     */
    public function extractCustomerRef(object $activity): ?string
    {
        return $this->extractRefValue($activity->CustomerRef ?? null);
    }

    /**
     * Extracts a project reference from a QuickBooks time activity row.
     *
     * @param  object  $activity  QuickBooks TimeActivity entity.
     * @return string|null
     */
    public function extractProjectRef(object $activity): ?string
    {
        return $this->extractRefValue($activity->ProjectRef ?? null);
    }

    /**
     * Extracts a service item reference from a QuickBooks time activity row.
     *
     * @param  object  $activity  QuickBooks TimeActivity entity.
     * @return string|null
     */
    public function extractItemRef(object $activity): ?string
    {
        return $this->extractRefValue($activity->ItemRef ?? null);
    }

    /**
     * Reads a QuickBooks reference value from a time activity field.
     *
     * @param  mixed  $activity  QuickBooks TimeActivity entity or array row.
     * @param  string  $field  Reference field name (for example `CustomerRef`).
     * @return string|null
     */
    public function activityRef(mixed $activity, string $field): ?string
    {
        if (is_array($activity)) {
            return $this->extractRefValue($activity[$field] ?? null);
        }

        if (! is_object($activity)) {
            return null;
        }

        return $this->extractRefValue($activity->{$field} ?? null);
    }

    /**
     * Loads customer entities for the given identifiers in bounded batches.
     *
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  list<string>  $customerRefs  QuickBooks customer identifiers.
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap per query.
     * @return list<object>
     */
    private function fetchCustomersByIds(object $dataService, array $customerRefs, int $maxResults): array
    {
        $refs = $this->uniqueRefs($customerRefs);

        if ($refs === []) {
            return [];
        }

        $entities = [];

        foreach (array_chunk($refs, 100) as $chunk) {
            $result = $dataService->Query(QboCustomerQuery::customersByIds($chunk, $maxResults));

            if ($error = $dataService->getLastError()) {
                abort($this->apiErrors->jsonResponse($error));
            }

            $entities = [...$entities, ...QboQueryResult::entities($result)];
        }

        return $entities;
    }

    /**
     * Normalizes a list of reference strings.
     *
     * @param  list<string>  $customerRefs  Raw customer identifiers.
     * @return list<string>
     */
    private function uniqueRefs(array $customerRefs): array
    {
        $refs = [];

        foreach ($customerRefs as $ref) {
            $normalized = preg_replace('/\D+/', '', (string) $ref) ?? ''; // @pest-mutate-ignore normalize reference identifiers

            if ($normalized !== '') {
                $refs[$normalized] = $normalized;
            }
        }

        return array_values($refs);
    }

    /**
     * Reads a QuickBooks reference value from an SDK aggregate.
     *
     * @param  mixed  $reference  QuickBooks reference aggregate.
     * @return string|null
     */
    private function extractRefValue(mixed $reference): ?string
    {
        if (is_object($reference) && isset($reference->value)) {
            $value = (string) $reference->value; // @pest-mutate-ignore normalize QBO reference values

            return $value !== '' ? $value : null;
        }

        if (is_string($reference) || is_int($reference)) {
            $value = (string) $reference; // @pest-mutate-ignore normalize QBO reference values

            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * Returns whether a customer entity is active in QuickBooks.
     *
     * @param  object  $customer  QuickBooks Customer entity.
     * @return bool
     */
    private function isActive(object $customer): bool
    {
        return ! isset($customer->Active) || (bool) $customer->Active; // @pest-mutate-ignore QBO customers default to active when flag is absent
    }

    /**
     * Returns whether a customer entity is a job or sub-customer.
     *
     * @param  object  $customer  QuickBooks Customer entity.
     * @return bool
     */
    private function isJobCustomer(object $customer): bool
    {
        return (bool) ($customer->Job ?? false); // @pest-mutate-ignore QBO job flag defaults to false when absent
    }

    /**
     * Reads the parent customer reference from a job customer entity.
     *
     * @param  object  $customer  QuickBooks Customer entity.
     * @return string|null
     */
    private function extractParentRef(object $customer): ?string
    {
        return $this->extractRefValue($customer->ParentRef ?? null);
    }

    /**
     * Maps a QuickBooks customer entity to a JSON-friendly shape.
     *
     * @param  object  $customer  QuickBooks Customer entity.
     * @return array{id: string, display_name: string}
     */
    private function normalizeCustomer(object $customer): array
    {
        return [
            'id' => (string) ($customer->Id ?? ''), // @pest-mutate-ignore normalize optional QBO customer fields
            'display_name' => (string) ($customer->DisplayName ?? ''), // @pest-mutate-ignore normalize optional QBO customer fields
        ];
    }
}
