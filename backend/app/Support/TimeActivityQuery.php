<?php

/**
 * QuickBooks SQL query helpers for time activity list operations.
 */

namespace App\Support;

/**
 * Builds escaped QuickBooks query strings for time activity reads.
 */
final class TimeActivityQuery
{
    /**
     * Builds a paginated SELECT for time activities within a transaction date window.
     *
     * QuickBooks does not allow filtering TimeActivity by EmployeeRef in queries.
     * Employee scoping is applied in PHP after the query returns.
     *
     * @param  int  $startPosition  QuickBooks STARTPOSITION (1-based).
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap.
     * @param  string  $minTxnDate  Inclusive lower bound (YYYY-MM-DD).
     * @return string
     */
    public static function listPage(int $startPosition, int $maxResults, string $minTxnDate): string
    {
        return "SELECT * FROM TimeActivity WHERE TxnDate >= '"
            .self::escapeValue($minTxnDate)
            ."' STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
    }

    /**
     * Escapes a value for a single-quoted QBO query.
     *
     * @param  string  $value  Raw value to escape for a QBO query.
     * @return string
     */
    public static function escapeValue(string $value): string
    {
        return str_replace("'", "\\'", $value);
    }
}
