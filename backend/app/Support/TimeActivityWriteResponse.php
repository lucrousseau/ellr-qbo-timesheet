<?php

/**
 * Helpers for persisting QuickBooks time activity write responses locally.
 */

namespace App\Support;

use App\Models\QuickBooksToken;
use App\Services\TimeActivitySnapshotService;
use App\Services\TimeActivitySyncService;

/**
 * Merges sparse QBO write payloads and upserts the local snapshot read model.
 */
class TimeActivityWriteResponse
{
    /**
     * Merges a sparse QuickBooks write response onto the loaded entity.
     *
     * @param  object  $existing  Activity loaded before the write.
     * @param  object  $written  Entity returned by QuickBooks Add or Update.
     * @return object
     */
    public static function merge(object $existing, object $written): object
    {
        return (object) array_merge((array) $existing, (array) $written);
    }

    /**
     * Upserts the local snapshot from a QuickBooks write response.
     *
     * @param  TimeActivitySnapshotService  $snapshots  Local snapshot persistence.
     * @param  TimeActivitySyncService  $sync  Fallback single-entity sync when write responses are sparse.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  object  $activity  QuickBooks time activity entity returned by the write.
     * @return void
     */
    public static function persistSnapshot(
        TimeActivitySnapshotService $snapshots,
        TimeActivitySyncService $sync,
        QuickBooksToken $token,
        object $dataService,
        object $activity,
    ): void {
        try {
            $snapshots->upsertFromQboEntity(
                $token->realm_id,
                $dataService,
                $activity,
                resolveMissingNames: false,
            );
        } catch (\InvalidArgumentException) {
            $id = isset($activity->Id) ? (string) $activity->Id : '';

            if ($id === '') {
                throw new \InvalidArgumentException('QuickBooks write response is missing a time activity id.');
            }

            $sync->syncOneById($token, $id, resolveMissingNames: false);
        }
    }
}
