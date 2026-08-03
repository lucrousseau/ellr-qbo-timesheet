<?php

/**
 * QuickBooks SQL query builders for Account and Vendor expense pickers.
 */

namespace App\Support;

/**
 * Builds sanitized QuickBooks query strings for expense account and vendor lists.
 */
class QboExpenseQuery
{
    /**
     * Lists active Chart of Accounts rows for expense and payment pickers.
     *
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap.
     * @return string
     */
    public static function listAccounts(int $maxResults): string
    {
        $maxResults = max(1, $maxResults);

        return "SELECT Id, Name, AccountType, AccountSubType FROM Account WHERE Active = true MAXRESULTS {$maxResults}";
    }

    /**
     * Lists active vendors for expense EntityRef pickers.
     *
     * @param  int  $maxResults  QuickBooks MAXRESULTS cap.
     * @return string
     */
    public static function listVendors(int $maxResults): string
    {
        $maxResults = max(1, $maxResults);

        return "SELECT Id, DisplayName FROM Vendor WHERE Active = true MAXRESULTS {$maxResults}";
    }
}
