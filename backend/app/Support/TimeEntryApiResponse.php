<?php

/**
 * Serializes local time entries for JSON API responses.
 */

namespace App\Support;

use App\Enums\TimeEntryStatus;
use App\Models\TimeActivitySnapshot;
use App\Models\TimeEntry;
use App\Services\OrganizationTimezoneService;
use Illuminate\Support\Collection;

/**
 * Builds time entry payloads exposed to timesheet and admin clients.
 */
class TimeEntryApiResponse
{
    /**
     * Maps a time entry model to an API-friendly array.
     *
     * @param  TimeEntry  $entry  Local time entry instance.
     * @return array<string, mixed>
     */
    public static function resource(TimeEntry $entry): array
    {
        $entry->loadMissing(['user', 'reviewedBy']);

        [$startTime, $endTime] = TimeActivityDuration::normalizeRange($entry->start_time, $entry->end_time);
        $durationSeconds = TimeActivityDuration::secondsBetween($startTime, $endTime);

        return [
            'id' => $entry->id,
            'list_id' => 'local:'.$entry->id,
            'user_id' => $entry->user_id,
            'employee_name' => $entry->user?->name,
            'customer_ref' => $entry->customer_ref,
            'customer_name' => $entry->customer_name,
            'project_ref' => $entry->project_ref,
            'project_name' => $entry->project_name,
            'item_ref' => $entry->item_ref,
            'item_name' => $entry->item_name,
            'start_time' => $startTime?->toIso8601String(),
            'end_time' => $endTime?->toIso8601String(),
            'duration_seconds' => $durationSeconds,
            'description' => $entry->description,
            'is_billable' => $entry->is_billable,
            'status' => $entry->status->value,
            'reviewed_by_id' => $entry->reviewed_by_id,
            'reviewed_by_name' => $entry->reviewedBy?->name,
            'reviewed_at' => $entry->reviewed_at?->toIso8601String(),
            'rejection_reason' => $entry->rejection_reason,
            'qbo_id' => $entry->qbo_id,
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }

    /**
     * Maps a collection of time entries to API payloads.
     *
     * @param  Collection<int, TimeEntry>  $entries  Time entry collection.
     * @return list<array<string, mixed>>
     */
    public static function collection(Collection $entries): array
    {
        return $entries
            ->map(fn (TimeEntry $entry): array => self::resource($entry))
            ->values()
            ->all();
    }

    /**
     * Maps a QuickBooks snapshot into the unified time entry list shape.
     *
     * Legacy QBO-only rows are treated as already approved.
     *
     * @param  TimeActivitySnapshot  $snapshot  Synced QuickBooks snapshot row.
     * @return array<string, mixed>
     */
    public static function fromSnapshot(TimeActivitySnapshot $snapshot): array
    {
        $companyTimezone = app(OrganizationTimezoneService::class)->forRealm($snapshot->realm_id);
        $durationSeconds = TimeActivityDuration::qboDurationSeconds(
            $snapshot->start_time,
            $snapshot->end_time,
            $companyTimezone,
        );

        return [
            'id' => null,
            'list_id' => 'qbo:'.$snapshot->qbo_id,
            'user_id' => null,
            'employee_name' => null,
            'customer_ref' => $snapshot->customer_ref,
            'customer_name' => self::customerLabel($snapshot),
            'project_ref' => $snapshot->project_ref,
            'project_name' => $snapshot->project_name,
            'item_ref' => $snapshot->item_ref,
            'item_name' => $snapshot->item_name,
            'start_time' => $snapshot->start_time?->toIso8601String(),
            'end_time' => $snapshot->end_time?->toIso8601String(),
            'duration_seconds' => $durationSeconds,
            'description' => $snapshot->description,
            'is_billable' => $snapshot->billable_status === 'Billable',
            'status' => TimeEntryStatus::Approved->value,
            'reviewed_by_id' => null,
            'reviewed_by_name' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'qbo_id' => $snapshot->qbo_id,
            'created_at' => $snapshot->last_synced_at?->toIso8601String(),
        ];
    }

    /**
     * Builds the client or project label shown in unified time entry lists.
     *
     * @param  TimeActivitySnapshot  $snapshot  Synced QuickBooks snapshot row.
     * @return string|null
     */
    private static function customerLabel(TimeActivitySnapshot $snapshot): ?string
    {
        $customer = $snapshot->customer_name;
        $project = $snapshot->project_name;

        if ($customer !== null && $project !== null && $customer !== $project) {
            return $customer.':'.$project;
        }

        return $customer ?? $project;
    }
}
