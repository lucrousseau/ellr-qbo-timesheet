<?php

/**
 * Read API for QuickBooks sync groups used by accountant drill-down views.
 */

namespace App\Services;

use App\Models\TimeEntrySyncGroup;
use App\Models\User;
use App\Support\TimeEntrySyncGroupDetailUrl;

/**
 * Loads sync groups the actor may inspect for audit and QBO note deep links.
 */
class TimeEntrySyncGroupService
{
    /**
     * Injects authorization and presentation helpers.
     *
     * @param  TimeEntryAuthorizationService  $authorization  Sync group visibility checks.
     * @param  TimeEntryPresentationService  $presentation  Read-time label resolution for member rows.
     */
    public function __construct(
        private readonly TimeEntryAuthorizationService $authorization,
        private readonly TimeEntryPresentationService $presentation,
    ) {}

    /**
     * Returns a sync group detail payload for the actor.
     *
     * @param  User  $actor  Authenticated employee, supervisor, or administrator.
     * @param  string  $publicId  Sync group UUID from the QuickBooks note link.
     * @return array<string, mixed>
     */
    public function showForActor(User $actor, string $publicId): array
    {
        $group = TimeEntrySyncGroup::query()
            ->with(['user', 'timeEntries.user', 'timeEntries.reviewedBy', 'timeEntries.syncGroup'])
            ->where('public_id', $publicId)
            ->firstOr(function (): never {
                abort(response()->json([
                    'error' => 'time_entry_sync_group_not_found',
                    'message' => __('api.time_entry_sync_group_not_found'),
                ], 404));
            });

        $this->authorization->assertCanViewSyncGroup($actor, $group);

        $members = $group->timeEntries
            ->sortBy([
                ['start_time', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        return [
            'data' => [
                'id' => $group->id,
                'public_id' => $group->public_id,
                'user_id' => $group->user_id,
                'employee_name' => $group->user?->name,
                'txn_date' => $group->txn_date?->toDateString(),
                'customer_ref' => $group->customer_ref,
                'project_ref' => $group->project_ref,
                'item_ref' => $group->item_ref,
                'is_billable' => $group->is_billable,
                'member_count' => $group->member_count,
                'total_duration_seconds' => $group->total_duration_seconds,
                'qbo_id' => $group->qbo_id,
                'qbo_description' => $group->qbo_description,
                'synced_at' => $group->synced_at?->toIso8601String(),
                'detail_url' => TimeEntrySyncGroupDetailUrl::forPublicId($group->public_id),
                'entries' => $this->presentation->collection($members, $actor),
            ],
        ];
    }
}
