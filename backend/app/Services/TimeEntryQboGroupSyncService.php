<?php

/**
 * Aggregates approved local time entries into one QuickBooks TimeActivity.
 */

namespace App\Services;

use App\Enums\TimeEntryStatus;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\TimeEntrySyncGroup;
use App\Models\User;
use App\Support\TimeEntryGroupedQboPayload;
use App\Support\TimeEntrySyncGroupDetailUrl;
use App\Support\TimeEntrySyncGroupKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Locks sibling approved entries and pushes a single grouped QuickBooks activity.
 */
class TimeEntryQboGroupSyncService
{
    /**
     * Injects QuickBooks writers and label resolution helpers.
     *
     * @param  TimeActivityService  $timeActivities  QuickBooks time activity writer.
     * @param  QboPickerDisplayNameService  $displayNames  Cached QuickBooks label resolver.
     * @param  OrganizationTimezoneService  $timezones  Company timezone resolver.
     */
    public function __construct(
        private readonly TimeActivityService $timeActivities,
        private readonly QboPickerDisplayNameService $displayNames,
        private readonly OrganizationTimezoneService $timezones,
    ) {}

    /**
     * Synchronizes the trigger entry with any approved unsynced siblings sharing its group key.
     *
     * @param  TimeEntry  $trigger  Approved local entry that queued synchronization.
     * @param  User  $employee  Entry owner linked to a QuickBooks employee.
     * @param  QuickBooksToken  $token  Organization QuickBooks token.
     * @return TimeEntrySyncGroup|null Created group when work was performed.
     */
    public function syncApprovedGroup(TimeEntry $trigger, User $employee, QuickBooksToken $token): ?TimeEntrySyncGroup
    {
        $timezone = $this->timezones->forOrganization($trigger->organization);
        $key = TimeEntrySyncGroupKey::fromEntry($trigger, $timezone);

        return DB::transaction(function () use ($trigger, $employee, $token, $timezone, $key): ?TimeEntrySyncGroup {
            $entries = $this->lockSiblingEntries($trigger, $key['group_key'], $timezone);

            if ($entries->isEmpty()) {
                return null;
            }

            /** @var TimeEntry $first */
            $first = $entries->first();
            $labels = $this->displayNames->entryDisplayNames($token, $employee, $first);
            $publicId = (string) Str::uuid(); // @pest-mutate-ignore uuid string cast
            $detailUrl = TimeEntrySyncGroupDetailUrl::forPublicId($publicId);
            $aggregated = TimeEntryGroupedQboPayload::fromEntries($entries, $detailUrl, $timezone, $labels);

            $group = TimeEntrySyncGroup::query()->create([
                'public_id' => $publicId,
                'organization_id' => $first->organization_id,
                'user_id' => $first->user_id,
                'txn_date' => $key['txn_date'],
                'customer_ref' => $first->customer_ref,
                'project_ref' => $first->project_ref,
                'item_ref' => $first->item_ref,
                'is_billable' => (bool) $first->is_billable,
                'group_key' => $key['group_key'],
                'member_count' => $aggregated['member_count'],
                'total_duration_seconds' => $aggregated['total_duration_seconds'],
                'qbo_description' => $aggregated['payload']['description'] ?? null, // @pest-mutate-ignore optional description fallback
            ]);

            $qboActivity = $this->timeActivities->createForUser($employee, $token, $aggregated['payload']);
            $qboId = (string) $qboActivity->Id; // @pest-mutate-ignore qbo id string cast

            TimeEntry::query()
                ->whereKey($entries->pluck('id')->all())
                ->where('status', TimeEntryStatus::Approved)
                ->whereNull('qbo_id')
                ->update([
                    'qbo_id' => $qboId,
                    'sync_group_id' => $group->id,
                ]);

            $group->forceFill([
                'qbo_id' => $qboId,
                'synced_at' => now(),
            ])->save();

            return $group->refresh();
        });
    }

    /**
     * Locks approved unsynced siblings that share the trigger grouping key.
     *
     * @param  TimeEntry  $trigger  Entry that initiated the sync job.
     * @param  string  $groupKey  Stable grouping key for the trigger.
     * @param  string  $timezone  Company timezone for date comparison.
     * @return Collection<int, TimeEntry>
     */
    private function lockSiblingEntries(TimeEntry $trigger, string $groupKey, string $timezone): Collection
    {
        if (! $trigger->group_for_qbo) {
            $solo = TimeEntry::query()
                ->whereKey($trigger->id)
                ->where('status', TimeEntryStatus::Approved)
                ->whereNull('qbo_id')
                ->whereNull('sync_group_id')
                ->lockForUpdate()
                ->get();

            return $solo->values();
        }

        $candidates = TimeEntry::query()
            ->where('organization_id', $trigger->organization_id)
            ->where('user_id', $trigger->user_id)
            ->where('status', TimeEntryStatus::Approved)
            ->where('group_for_qbo', true)
            ->whereNull('qbo_id')
            ->whereNull('sync_group_id')
            ->orderBy('start_time')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return $candidates
            ->filter(function (TimeEntry $entry) use ($groupKey, $timezone): bool {
                $key = TimeEntrySyncGroupKey::fromEntry($entry, $timezone);

                return $key['group_key'] === $groupKey;
            })
            ->values();
    }
}
