<?php

/**
 * Origins for structured ticket keys attached to time capture.
 */

namespace App\Enums;

/**
 * Backed enum of ticket providers used when logging time.
 */
enum TicketSource: string
{
    case Manual = 'manual';
    case Jira = 'jira';
    case Linear = 'linear';

    /**
     * Returns whether the value is a known ticket source.
     *
     * @param  string|null  $value  Candidate source string.
     * @return bool
     */
    public static function isValid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return self::tryFrom($value) !== null;
    }
}
