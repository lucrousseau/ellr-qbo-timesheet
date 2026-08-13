<?php

/**
 * Builds stable grouping keys for approved time entries destined for QuickBooks.
 */

namespace App\Support;

use App\Models\TimeEntry;
use Illuminate\Support\Carbon;

/**
 * Derives the dimensions used to coalesce local entries into one QBO TimeActivity.
 */
final class TimeEntrySyncGroupKey
{
    /**
     * Builds the grouping key and transaction date for a local time entry.
     *
     * Entries share a QuickBooks activity when employee, calendar day (company
     * timezone), customer/project, service item, and billable flag match.
     *
     * @param  TimeEntry  $entry  Local time entry being synchronized.
     * @param  string  $timezone  Company timezone used for the transaction date.
     * @return array{group_key: string, txn_date: string}
     */
    public static function fromEntry(TimeEntry $entry, string $timezone): array
    {
        $txnDate = ($entry->start_time ?? Carbon::now())
            ->copy()
            ->timezone($timezone)
            ->toDateString();

        $parts = [
            (string) $entry->organization_id,
            (string) $entry->user_id,
            $txnDate,
            self::normalizeRef($entry->customer_ref),
            self::normalizeRef($entry->project_ref),
            self::normalizeRef($entry->item_ref),
            $entry->is_billable ? '1' : '0',
        ];

        return [
            'group_key' => implode('|', $parts),
            'txn_date' => $txnDate,
        ];
    }

    /**
     * Normalizes an optional QuickBooks reference for key comparison.
     *
     * @param  string|null  $value  Raw reference value.
     * @return string
     */
    private static function normalizeRef(?string $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }
}
