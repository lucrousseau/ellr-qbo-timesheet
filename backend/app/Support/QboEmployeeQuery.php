<?php

/**
 * QuickBooks SQL query builders for Employee entities.
 */

namespace App\Support;

/**
 * Builds sanitized QuickBooks query strings for employee lookups.
 */
class QboEmployeeQuery
{
    /**
     * Lists active employees with display name and optional email.
     *
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap.
     * @return string
     */
    public static function listActive(int $maxResults): string
    {
        $maxResults = max(1, $maxResults);

        return "SELECT Id, DisplayName, PrimaryEmailAddr FROM Employee WHERE Active = true MAXRESULTS {$maxResults}";
    }
}
