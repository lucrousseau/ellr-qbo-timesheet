<?php

/**
 * Composes QuickBooks TimeActivity Description values from ticket metadata.
 */

namespace App\Support;

/**
 * Builds the Description field synced to QuickBooks from local ticket + notes.
 */
final class TimeEntryQboDescription
{
    /**
     * Prefixed ticket key and free-text description for QuickBooks Description.
     *
     * @param  string|null  $ticketKey  Structured ticket key (for example PROJ-123).
     * @param  string|null  $description  Employee free-text work notes.
     * @return string|null
     */
    public static function compose(?string $ticketKey, ?string $description): ?string
    {
        $key = is_string($ticketKey) ? trim($ticketKey) : '';
        $notes = is_string($description) ? trim($description) : '';

        if ($key === '' && $notes === '') {
            return null;
        }

        if ($key === '') {
            return $notes;
        }

        if ($notes === '') {
            return '['.$key.']';
        }

        return '['.$key.'] '.$notes;
    }
}
