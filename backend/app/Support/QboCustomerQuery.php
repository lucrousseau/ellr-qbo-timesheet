<?php

/**
 * QuickBooks SQL query builders for Customer and Item entities.
 */

namespace App\Support;

/**
 * Builds sanitized QuickBooks query strings for customer and service pickers.
 */
class QboCustomerQuery
{
    /**
     * Lists active top-level customers (non-job customers).
     *
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap.
     * @return string
     */
    public static function listCustomers(int $maxResults): string
    {
        $maxResults = max(1, $maxResults);

        return "SELECT Id, DisplayName FROM Customer WHERE Active = true AND Job = false MAXRESULTS {$maxResults}";
    }

    /**
     * Loads customer entities for a bounded list of identifiers.
     *
     * @param  list<string>  $customerRefs  QuickBooks customer identifiers.
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap.
     * @return string
     */
    public static function customersByIds(array $customerRefs, int $maxResults): string
    {
        $ids = array_values(array_filter(array_map(
            fn (string $ref): string => preg_replace('/\D+/', '', $ref) ?? '',
            $customerRefs,
        )));

        if ($ids === []) {
            return "SELECT Id, DisplayName, Job, ParentRef, Active FROM Customer WHERE Id = '0' MAXRESULTS 1";
        }

        $quotedIds = implode("','", $ids);
        $maxResults = max(1, $maxResults);

        return "SELECT Id, DisplayName, Job, ParentRef, Active FROM Customer WHERE Id IN ('{$quotedIds}') MAXRESULTS {$maxResults}";
    }

    /**
     * Lists active job customers (projects/sub-customers).
     *
     * ParentRef is not queryable in QuickBooks Online; callers filter by parent in PHP.
     *
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap per page.
     * @param  int  $startPosition  QuickBooks STARTPOSITION (1-based).
     * @return string
     */
    public static function listActiveJobCustomers(int $maxResults, int $startPosition = 1): string
    {
        $maxResults = max(1, $maxResults);
        $startPosition = max(1, $startPosition);

        return "SELECT Id, DisplayName, ParentRef FROM Customer WHERE Active = true AND Job = true STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
    }

    /**
     * Lists active service items for time tracking.
     *
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap.
     * @return string
     */
    public static function listServices(int $maxResults): string
    {
        $maxResults = max(1, $maxResults);

        return "SELECT Id, Name FROM Item WHERE Active = true AND Type = 'Service' MAXRESULTS {$maxResults}";
    }
}
