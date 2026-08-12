<?php

/**
 * Builds aggregated QuickBooks payloads and audit descriptions for sync groups.
 */

namespace App\Support;

use App\Models\TimeEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates sibling local entries into one QuickBooks create payload.
 */
final class TimeEntryGroupedQboPayload
{
    /**
     * Maximum characters kept for the QuickBooks Description field.
     */
    private const DESCRIPTION_MAX_LENGTH = 3900;

    /**
     * Builds a QuickBooks create payload from locked sibling entries.
     *
     * @param  Collection<int, TimeEntry>  $entries  Approved unsynced siblings ordered by start time.
     * @param  string  $detailUrl  Absolute URL for accountant drill-down in Ellr.
     * @param  string  $timezone  Company timezone for clock labels in the description.
     * @param  array{customer_name?: string|null, project_name?: string|null, item_name?: string|null}  $labels  Resolved display labels.
     * @return array{payload: array<string, mixed>, total_duration_seconds: int, member_count: int}
     */
    public static function fromEntries(
        Collection $entries,
        string $detailUrl,
        string $timezone,
        array $labels = [],
    ): array {
        /** @var TimeEntry $first */
        $first = $entries->firstOrFail();
        $totalSeconds = 0;

        foreach ($entries as $entry) {
            [$start, $end] = TimeActivityDuration::normalizeRange($entry->start_time, $entry->end_time);
            $totalSeconds += TimeActivityDuration::secondsBetween($start, $end);
        }

        $startTime = $entries
            ->map(fn (TimeEntry $entry): Carbon => $entry->start_time ?? Carbon::now())
            ->sortBy(fn (Carbon $time): int => $time->getTimestamp())
            ->first();

        if (! $startTime instanceof Carbon) {
            $startTime = Carbon::now();
        }

        $endTime = $startTime->copy()->addSeconds(max(0, $totalSeconds));
        $description = self::description($entries, $detailUrl, $timezone, $totalSeconds);

        $payload = TimeEntryQboPayload::fromEntry($first, $labels);
        $payload['start_time'] = $startTime->toIso8601String();
        $payload['end_time'] = $endTime->toIso8601String();
        $payload['description'] = $description;
        $payload['is_billable'] = (bool) $first->is_billable;

        return [
            'payload' => $payload,
            'total_duration_seconds' => $totalSeconds,
            'member_count' => $entries->count(),
        ];
    }

    /**
     * Builds the QuickBooks note with a deep link and member summary.
     *
     * @param  Collection<int, TimeEntry>  $entries  Group members.
     * @param  string  $detailUrl  Absolute Ellr detail URL.
     * @param  string  $timezone  Company timezone for local clock labels.
     * @param  int  $totalSeconds  Aggregated duration in seconds.
     * @return string
     */
    public static function description(
        Collection $entries,
        string $detailUrl,
        string $timezone,
        int $totalSeconds,
    ): string {
        $count = $entries->count();
        $hoursLabel = self::formatDuration($totalSeconds);
        $lines = [
            sprintf('Ellr grouped time: %d %s, %s.', $count, $count === 1 ? 'entry' : 'entries', $hoursLabel),
            'Details: '.$detailUrl,
        ];

        foreach ($entries as $entry) {
            $start = $entry->start_time?->copy()->timezone($timezone);
            $end = $entry->end_time?->copy()->timezone($timezone);
            $clock = sprintf(
                '%s-%s',
                $start?->format('H:i') ?? '--:--',
                $end?->format('H:i') ?? '--:--',
            );
            $note = trim((string) ($entry->description ?? ''));
            $lines[] = $note !== '' ? '- '.$clock.' '.$note : '- '.$clock;
        }

        $description = implode("\n", $lines);

        if (strlen($description) <= self::DESCRIPTION_MAX_LENGTH) {
            return $description;
        }

        return substr($description, 0, self::DESCRIPTION_MAX_LENGTH - 3).'...';
    }

    /**
     * Formats a duration as compact hours and minutes for QuickBooks notes.
     *
     * @param  int  $seconds  Duration in seconds.
     * @return string
     */
    private static function formatDuration(int $seconds): string
    {
        $hours = intdiv(max(0, $seconds), 3600);
        $minutes = intdiv(max(0, $seconds) % 3600, 60);

        return sprintf('%dh%02d', $hours, $minutes);
    }
}
