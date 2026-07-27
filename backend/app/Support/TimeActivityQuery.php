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
     * Builds a SELECT query for an employee's time activities with pagination.
     *
     * @param  string  $employeeRef  QuickBooks employee reference.
     * @param  int  $startPosition  QuickBooks STARTPOSITION (1-based).
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap.
     * @return string
     */
    public static function listForEmployee(string $employeeRef, int $startPosition, int $maxResults): string
    {
        return "SELECT * FROM TimeActivity WHERE EmployeeRef = '".self::escapeValue($employeeRef)."' STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
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
