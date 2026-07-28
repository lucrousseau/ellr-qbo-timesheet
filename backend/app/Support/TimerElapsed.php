<?php

/**
 * Timer elapsed-time helpers for active session rows.
 */

namespace App\Support;

use App\Models\ActiveTimeSession;
use Illuminate\Support\Carbon;

/**
 * Computes capped elapsed seconds from persisted timer session attributes.
 */
class TimerElapsed
{
    /**
     * Returns total elapsed seconds for an active session, including a running segment.
     *
     * @param  ActiveTimeSession  $session  Active session row.
     * @return int
     */
    public static function forSession(ActiveTimeSession $session): int
    {
        $elapsed = $session->accumulated_seconds;
        $runningSince = self::asCarbon($session->running_since);

        if ($runningSince !== null) {
            $elapsed += max(0, $runningSince->diffInSeconds(now()));
        }

        return self::cap($elapsed);
    }

    /**
     * Caps accumulated elapsed seconds to the configured maximum.
     *
     * @param  int  $seconds  Raw elapsed seconds.
     * @return int
     */
    public static function cap(int $seconds): int
    {
        return min(max(0, $seconds), self::maxSeconds());
    }

    /**
     * Returns the maximum allowed accumulated timer seconds.
     *
     * @return int
     */
    public static function maxSeconds(): int
    {
        return max(1, (int) config('quickbooks.time_tracker_max_accumulated_seconds', 86400));
    }

    /**
     * Normalizes a persisted datetime attribute to Carbon when possible.
     *
     * @param  mixed  $value  Raw model attribute value.
     * @return Carbon|null
     */
    public static function asCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }
}
