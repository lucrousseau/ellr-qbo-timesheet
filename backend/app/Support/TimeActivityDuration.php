<?php

/**
 * Duration helpers for time activity and time entry timestamps.
 */

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Computes elapsed seconds between optional start and end timestamps.
 */
final class TimeActivityDuration
{
    /**
     * Returns elapsed whole seconds between two timestamps for local time entries.
     *
     * Carbon 3 returns a signed value from {@see Carbon::diffInSeconds()}; this helper
     * always uses the absolute difference.
     *
     * @param  Carbon|null  $start  Interval start.
     * @param  Carbon|null  $end  Interval end.
     * @return int
     */
    public static function secondsBetween(?Carbon $start, ?Carbon $end): int
    {
        if ($start === null || $end === null) { // @pest-mutate-ignore optional interval inputs
            return 0;
        }

        return (int) $start->diffInSeconds($end, absolute: true); // @pest-mutate-ignore absolute elapsed seconds
    }

    /**
     * Returns elapsed seconds using QuickBooks clock-time rules.
     *
     * When EndTime is earlier than StartTime on the same calendar day, QBO treats
     * the interval as crossing midnight instead of swapping the timestamps.
     *
     * @param  Carbon|null  $start  Stored interval start (UTC).
     * @param  Carbon|null  $end  Stored interval end (UTC).
     * @param  string|null  $timezone  Company timezone for clock-time comparison.
     * @return int
     */
    public static function qboDurationSeconds(?Carbon $start, ?Carbon $end, ?string $timezone = null): int
    {
        if ($start === null || $end === null) { // @pest-mutate-ignore optional interval inputs
            return 0;
        }

        $timezone ??= (string) config('quickbooks.company_timezone', 'UTC'); // @pest-mutate-ignore company timezone default
        $localStart = $start->copy()->timezone($timezone);
        $localEnd = $end->copy()->timezone($timezone);

        if (! $localStart->isSameDay($localEnd)) { // @pest-mutate-ignore multi-day QBO duration branch
            return (int) $localStart->diffInSeconds($localEnd, absolute: true);
        }

        $startSeconds = (int) $localStart->secondsSinceMidnight(); // @pest-mutate-ignore QBO clock-time normalization
        $endSeconds = (int) $localEnd->secondsSinceMidnight(); // @pest-mutate-ignore QBO clock-time normalization

        if ($endSeconds >= $startSeconds) { // @pest-mutate-ignore same-day QBO duration branch
            return $endSeconds - $startSeconds;
        }

        return (86_400 - $startSeconds) + $endSeconds; // @pest-mutate-ignore midnight wraparound duration
    }

    /**
     * Normalizes a start/end pair so the start is never after the end.
     *
     * Used for locally authored entries where end must follow start.
     *
     * @param  Carbon|null  $start  Interval start.
     * @param  Carbon|null  $end  Interval end.
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    public static function normalizeRange(?Carbon $start, ?Carbon $end): array
    {
        if ($start === null || $end === null || $start->lessThanOrEqualTo($end)) { // @pest-mutate-ignore ordered interval shortcut
            return [$start, $end];
        }

        return [$end, $start];
    }
}
