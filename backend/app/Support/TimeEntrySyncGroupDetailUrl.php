<?php

/**
 * Builds accountant-facing deep links for QuickBooks sync groups.
 */

namespace App\Support;

/**
 * Resolves the Ellr URL embedded in QuickBooks TimeActivity notes.
 */
final class TimeEntrySyncGroupDetailUrl
{
    /**
     * Returns an absolute admin deep link for a sync group public identifier.
     *
     * @param  string  $publicId  Sync group UUID exposed outside the API.
     * @return string
     */
    public static function forPublicId(string $publicId): string
    {
        $base = rtrim((string) config('app.frontend_admin_url'), '/');

        return $base.'/?sync_group='.rawurlencode($publicId);
    }
}
