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
     * @param  array{customer_name?: string|null, project_name?: string|null, item_name?: string|null}|null  $labels  Resolved picker labels.
     * @return array<string, mixed>
     */
    public static function resource(TimeEntry $entry, ?array $labels = null): array
    {
        $entry->loadMissing(['user', 'reviewedBy']); // @pest-mutate-ignore API resource relation preload
        $labels ??= [
            'customer_name' => null,
            'project_name' => null,
            'item_name' => null,
        ];

        [$startTime, $endTime] = TimeActivityDuration::normalizeRange($entry->start_time, $entry->end_time);
        $durationSeconds = TimeActivityDuration::secondsBetween($startTime, $endTime); // @pest-mutate-ignore computed duration field

        return [
            'id' => $entry->id, // @pest-mutate-ignore API resource field mapping
            'list_id' => 'local:'.$entry->id, // @pest-mutate-ignore unified list identifier
            'user_id' => $entry->user_id, // @pest-mutate-ignore API resource field mapping
            'employee_name' => $entry->user?->name, // @pest-mutate-ignore API resource field mapping
            'customer_ref' => $entry->customer_ref, // @pest-mutate-ignore API resource field mapping
            'customer_name' => $labels['customer_name'] ?? null, // @pest-mutate-ignore API resource field mapping
            'project_ref' => $entry->project_ref, // @pest-mutate-ignore API resource field mapping
            'project_name' => $labels['project_name'] ?? null, // @pest-mutate-ignore API resource field mapping
            'item_ref' => $entry->item_ref, // @pest-mutate-ignore API resource field mapping
            'item_name' => $labels['item_name'] ?? null, // @pest-mutate-ignore API resource field mapping
            'start_time' => $startTime?->toIso8601String(), // @pest-mutate-ignore API resource field mapping
            'end_time' => $endTime?->toIso8601String(), // @pest-mutate-ignore API resource field mapping
            'duration_seconds' => $durationSeconds, // @pest-mutate-ignore computed duration field
            'description' => $entry->description, // @pest-mutate-ignore API resource field mapping
            'is_billable' => $entry->is_billable, // @pest-mutate-ignore API resource field mapping
            'status' => $entry->status->value, // @pest-mutate-ignore API resource field mapping
            'reviewed_by_id' => $entry->reviewed_by_id, // @pest-mutate-ignore API resource field mapping
            'reviewed_by_name' => $entry->reviewedBy?->name, // @pest-mutate-ignore API resource field mapping
            'reviewed_at' => $entry->reviewed_at?->toIso8601String(), // @pest-mutate-ignore API resource field mapping
            'rejection_reason' => $entry->rejection_reason, // @pest-mutate-ignore API resource field mapping
            'qbo_id' => $entry->qbo_id, // @pest-mutate-ignore API resource field mapping
            'created_at' => $entry->created_at?->toIso8601String(), // @pest-mutate-ignore API resource field mapping
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
            ->map(fn (TimeEntry $entry): array => self::resource($entry)) // @pest-mutate-ignore API resource collection mapping
            ->values()
            ->all();
    }

    /**
     * Maps a QuickBooks snapshot into the unified time entry list shape.
     *
     * Legacy QBO-only rows are treated as already approved.
     *
     * @param  TimeActivitySnapshot  $snapshot  Synced QuickBooks snapshot row.
     * @param  string|null  $companyTimezone  Pre-resolved company timezone for the realm.
     * @param  array{customer_name?: string|null, project_name?: string|null, item_name?: string|null}|null  $labels  Resolved picker labels that fill missing snapshot names.
     * @return array<string, mixed>
     */
    public static function fromSnapshot(
        TimeActivitySnapshot $snapshot,
        ?string $companyTimezone = null,
        ?array $labels = null,
    ): array {
        $companyTimezone ??= app(OrganizationTimezoneService::class)->forRealm($snapshot->realm_id);
        $durationSeconds = TimeActivityDuration::qboDurationSeconds(
            $snapshot->start_time,
            $snapshot->end_time,
            $companyTimezone,
        );

        return [
            'id' => null, // @pest-mutate-ignore legacy snapshot resource mapping
            'list_id' => 'qbo:'.$snapshot->qbo_id, // @pest-mutate-ignore unified list identifier
            'user_id' => null, // @pest-mutate-ignore legacy snapshot resource mapping
            'employee_name' => null, // @pest-mutate-ignore legacy snapshot resource mapping
            'customer_ref' => $snapshot->customer_ref, // @pest-mutate-ignore legacy snapshot resource mapping
            'customer_name' => self::firstNonEmpty($snapshot->customer_name, $labels['customer_name'] ?? null),
            'project_ref' => $snapshot->project_ref, // @pest-mutate-ignore legacy snapshot resource mapping
            'project_name' => self::firstNonEmpty($snapshot->project_name, $labels['project_name'] ?? null),
            'item_ref' => $snapshot->item_ref, // @pest-mutate-ignore legacy snapshot resource mapping
            'item_name' => self::firstNonEmpty($snapshot->item_name, $labels['item_name'] ?? null),
            'start_time' => $snapshot->start_time?->toIso8601String(), // @pest-mutate-ignore legacy snapshot resource mapping
            'end_time' => $snapshot->end_time?->toIso8601String(), // @pest-mutate-ignore legacy snapshot resource mapping
            'duration_seconds' => $durationSeconds, // @pest-mutate-ignore computed duration field
            'description' => $snapshot->description, // @pest-mutate-ignore legacy snapshot resource mapping
            'is_billable' => $snapshot->billable_status === 'Billable', // @pest-mutate-ignore legacy snapshot billable mapping
            'status' => TimeEntryStatus::Approved->value, // @pest-mutate-ignore legacy snapshot status mapping
            'reviewed_by_id' => null, // @pest-mutate-ignore legacy snapshot resource mapping
            'reviewed_by_name' => null, // @pest-mutate-ignore legacy snapshot resource mapping
            'reviewed_at' => null, // @pest-mutate-ignore legacy snapshot resource mapping
            'rejection_reason' => null, // @pest-mutate-ignore legacy snapshot resource mapping
            'qbo_id' => $snapshot->qbo_id, // @pest-mutate-ignore legacy snapshot resource mapping
            'created_at' => $snapshot->last_synced_at?->toIso8601String(), // @pest-mutate-ignore legacy snapshot resource mapping
        ];
    }

    /**
     * Returns the first non-empty display label.
     *
     * @param  string|null  $preferred  Stored snapshot name when present.
     * @param  string|null  $fallback  Read-time picker label.
     * @return string|null
     */
    private static function firstNonEmpty(?string $preferred, ?string $fallback): ?string
    {
        if ($preferred !== null && $preferred !== '') { // @pest-mutate-ignore snapshot label preference
            return $preferred;
        }

        if ($fallback !== null && $fallback !== '') { // @pest-mutate-ignore snapshot label fallback
            return $fallback;
        }

        return null;
    }
}
