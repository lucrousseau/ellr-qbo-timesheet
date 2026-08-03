<?php

/**
 * Maps local time entries to QuickBooks time activity create payloads.
 */

namespace App\Support;

use App\Models\TimeEntry;

/**
 * Builds QuickBooks write payloads from stored refs and resolved labels.
 */
final class TimeEntryQboPayload
{
    /**
     * Maps a local entry and optional display labels to a QuickBooks create payload.
     *
     * @param  TimeEntry  $entry  Local time entry row.
     * @param  array{customer_name?: string|null, project_name?: string|null, item_name?: string|null}  $labels  Resolved display labels.
     * @return array<string, mixed>
     */
    public static function fromEntry(TimeEntry $entry, array $labels = []): array
    {
        $payload = [
            'start_time' => $entry->start_time?->toIso8601String(), // @pest-mutate-ignore qbo payload time mapping
            'end_time' => $entry->end_time?->toIso8601String(), // @pest-mutate-ignore qbo payload time mapping
            'description' => TimeEntryQboDescription::compose($entry->ticket_key, $entry->description),
            'is_billable' => $entry->is_billable,
        ];

        if ($entry->customer_ref) {
            $payload['customer_ref'] = $entry->customer_ref;

            if (! empty($labels['customer_name'])) {
                $payload['customer_name'] = $labels['customer_name'];
            }
        }

        if ($entry->project_ref) {
            $payload['project_ref'] = $entry->project_ref;

            if (! empty($labels['project_name'])) {
                $payload['project_name'] = $labels['project_name'];
            }
        }

        if ($entry->item_ref) {
            $payload['item_ref'] = $entry->item_ref;

            if (! empty($labels['item_name'])) {
                $payload['item_name'] = $labels['item_name'];
            }
        }

        return $payload;
    }
}
