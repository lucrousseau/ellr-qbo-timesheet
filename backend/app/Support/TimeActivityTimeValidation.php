<?php

/**
 * Shared validation for QuickBooks time activity start and end times.
 */

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

/**
 * Ensures end times are strictly after start times for time activity payloads.
 */
final class TimeActivityTimeValidation
{
    /**
     * User-facing message when end time is not after start time.
     */
    public const END_AFTER_START_MESSAGE = 'The end time field must be a date after start time.';

    /**
     * User-facing message when a partial time update cannot resolve both fields.
     */
    public const INCOMPLETE_TIME_FIELDS_MESSAGE = 'Start and end times are both required when updating time fields.';

    /**
     * Returns a normalized datetime string for validation and QBO payloads.
     *
     * @param  mixed  $time  Raw time value from the request or QBO SDK.
     * @return string|null
     */
    public static function normalizeTime(mixed $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return (string) $time;
    }

    /**
     * Returns true when end is strictly after start, or when either value is missing.
     *
     * @param  string|null  $startTime  ISO-like datetime string.
     * @param  string|null  $endTime  ISO-like datetime string.
     * @return bool
     */
    public static function isEndAfterStart(?string $startTime, ?string $endTime): bool
    {
        if ($startTime === null || $endTime === null) {
            return true;
        }

        return Carbon::parse($endTime)->gt(Carbon::parse($startTime));
    }

    /**
     * Aborts when a time-field PATCH cannot resolve both start and end times.
     *
     * @param  bool  $updatingTimeFields  Whether start_time or end_time was sent.
     * @param  string|null  $startTime  Resolved start time.
     * @param  string|null  $endTime  Resolved end time.
     * @return void
     */
    public static function abortWhenUpdateTimesIncomplete(
        bool $updatingTimeFields,
        ?string $startTime,
        ?string $endTime,
    ): void {
        if (! $updatingTimeFields || ($startTime !== null && $endTime !== null)) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => self::INCOMPLETE_TIME_FIELDS_MESSAGE,
            'errors' => [
                'start_time' => [self::INCOMPLETE_TIME_FIELDS_MESSAGE],
                'end_time' => [self::INCOMPLETE_TIME_FIELDS_MESSAGE],
            ],
        ], 422));
    }

    /**
     * Aborts with a 422 JSON validation response when end is not after start.
     *
     * @param  string|null  $startTime  Resolved start time.
     * @param  string|null  $endTime  Resolved end time.
     * @return void
     */
    public static function abortUnlessEndAfterStart(?string $startTime, ?string $endTime): void
    {
        if (! self::isEndAfterStart($startTime, $endTime)) {
            throw new HttpResponseException(response()->json([
                'message' => self::END_AFTER_START_MESSAGE,
                'errors' => [
                    'end_time' => [self::END_AFTER_START_MESSAGE],
                ],
            ], 422));
        }
    }
}
