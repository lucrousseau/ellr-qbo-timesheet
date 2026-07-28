<?php

/**
 * Normalizes QuickBooks SDK query responses into entity lists.
 */

namespace App\Support;

/**
 * QuickBooks DataService::Query may return an array or a single entity object.
 */
class QboQueryResult
{
    /**
     * Normalizes a QuickBooks query result into a zero-indexed list of entities.
     *
     * @param  mixed  $result  Raw QuickBooks query response.
     * @return array<int, object>
     */
    public static function entities(mixed $result): array
    {
        if (is_array($result)) {
            return array_values($result);
        }

        if (is_object($result)) {
            return [$result];
        }

        return [];
    }
}
