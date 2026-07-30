<?php

/**
 * Validates and normalizes IANA timezone identifiers.
 */

namespace App\Support;

/**
 * Guards timezone preference values against invalid IANA identifiers.
 */
final class TimezoneIdentifier
{
    /**
     * Returns whether the value is a supported IANA timezone identifier.
     *
     * @param  string|null  $timezone  Candidate timezone identifier.
     * @return bool
     */
    public static function isValid(?string $timezone): bool
    {
        if ($timezone === null) {
            return false;
        }

        $normalized = trim($timezone);

        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, timezone_identifiers_list(), true);
    }

    /**
     * Normalizes a timezone identifier or returns null when invalid or blank.
     *
     * @param  string|null  $timezone  Candidate timezone identifier.
     * @return string|null
     */
    public static function normalize(?string $timezone): ?string
    {
        if ($timezone === null) {
            return null;
        }

        $normalized = trim($timezone);

        if ($normalized === '' || ! self::isValid($normalized)) {
            return null;
        }

        return $normalized;
    }
}
