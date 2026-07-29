<?php

/**
 * Sort helpers for QuickBooks time activity query results.
 */

namespace App\Support;

/**
 * Sorts time activity rows by most recent work time.
 */
final class TimeActivitySorter
{
    /**
     * Sorts activities newest-first using start time, end time, then transaction date.
     *
     * QuickBooks does not support ORDERBY MetaData.LastUpdatedTime on TimeActivity.
     *
     * @param  array<int, mixed>  $activities  QuickBooks time activity rows.
     * @return array<int, mixed>
     */
    public static function sortByRecent(array $activities): array
    {
        usort($activities, static function (mixed $left, mixed $right): int {
            return self::sortTimestamp($right) <=> self::sortTimestamp($left);
        });

        return $activities;
    }

    /**
     * Resolves a comparable timestamp for sorting one activity row.
     *
     * @param  mixed  $activity  QuickBooks time activity row.
     * @return int
     */
    private static function sortTimestamp(mixed $activity): int
    {
        foreach (['StartTime', 'EndTime', 'TxnDate'] as $field) {
            $value = self::readField($activity, $field);

            if ($value === null) {
                continue;
            }

            $timestamp = strtotime($value);

            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return 0;
    }

    /**
     * Reads a scalar field from an object or array activity row.
     *
     * @param  mixed  $activity  QuickBooks time activity row.
     * @param  string  $field  Field name to read.
     * @return string|null
     */
    private static function readField(mixed $activity, string $field): ?string
    {
        if (is_object($activity) && isset($activity->{$field}) && is_string($activity->{$field})) {
            return $activity->{$field};
        }

        if (is_array($activity) && isset($activity[$field]) && is_string($activity[$field])) {
            return $activity[$field];
        }

        return null;
    }
}
