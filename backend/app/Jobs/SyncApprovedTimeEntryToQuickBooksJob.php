<?php

/**
 * Queued QuickBooks sync for approved local time entries, coalesced by group key.
 */

namespace App\Jobs;

use App\Enums\TimeEntryStatus;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\OrganizationTimezoneService;
use App\Services\TimeEntryQboGroupSyncService;
use App\Support\TimeEntrySyncGroupKey;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates one QuickBooks TimeActivity for all approved unsynced siblings in a group.
 */
class SyncApprovedTimeEntryToQuickBooksJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Seconds to deduplicate sync jobs for the same grouping key.
     */
    public int $uniqueFor;

    /**
     * @param  int  $timeEntryId  Local time entry primary key that triggered sync.
     * @param  int  $employeeId  Employee who logged the entry.
     * @param  int  $tokenId  Organization QuickBooks token primary key.
     * @param  string  $groupKey  Stable grouping key used for unique job coalescing.
     */
    public function __construct(
        public readonly int $timeEntryId,
        public readonly int $employeeId,
        public readonly int $tokenId,
        public readonly string $groupKey,
    ) {
        $this->uniqueFor = max(1, (int) config('quickbooks.time_entry_sync_group_unique_for', 120)); // @pest-mutate-ignore unique lock window config cast
    }

    /**
     * Deduplicates sync jobs so near-simultaneous approvals share one QuickBooks push.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return 'time-entry-sync-group:'.$this->groupKey;
    }

    /**
     * Pushes the approved group to QuickBooks while keeping approval decisions intact.
     *
     * @param  TimeEntryQboGroupSyncService  $groupSync  Grouped QuickBooks sync orchestrator.
     * @return void
     */
    public function handle(TimeEntryQboGroupSyncService $groupSync): void
    {
        $entry = TimeEntry::query()->with('organization')->find($this->timeEntryId);
        $employee = User::query()->find($this->employeeId);
        $token = QuickBooksToken::query()->find($this->tokenId);

        if (
            $entry === null
            || $employee === null
            || $token === null
            || $entry->status !== TimeEntryStatus::Approved
            || $entry->qbo_id !== null
        ) {
            return;
        }

        try {
            $groupSync->syncApprovedGroup($entry, $employee, $token);
        } catch (Throwable $exception) {
            Log::warning('QuickBooks synchronization failed for approved time entry group', [ // @pest-mutate-ignore approved sync failure observability
                'time_entry_id' => $entry->id, // @pest-mutate-ignore approved sync failure observability
                'employee_id' => $employee->id, // @pest-mutate-ignore approved sync failure observability
                'group_key' => $this->groupKey, // @pest-mutate-ignore approved sync failure observability
                'exception' => $exception->getMessage(), // @pest-mutate-ignore approved sync failure observability
            ]);

            throw new \RuntimeException('QuickBooks synchronization failed for time entry '.$entry->id, 0, $exception); // @pest-mutate-ignore approved sync failure propagation
        }
    }

    /**
     * Builds the unique grouping key for an approved entry about to be queued.
     *
     * @param  TimeEntry  $entry  Approved local entry.
     * @param  OrganizationTimezoneService  $timezones  Company timezone resolver.
     * @return string
     */
    public static function groupKeyFor(TimeEntry $entry, OrganizationTimezoneService $timezones): string
    {
        $entry->loadMissing('organization');
        $timezone = $timezones->forOrganization($entry->organization);

        return TimeEntrySyncGroupKey::fromEntry($entry, $timezone)['group_key'];
    }

    /**
     * Logs a terminal queue failure without reverting the approval decision.
     *
     * @param  Throwable  $exception  Queue failure reason.
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Approved time entry QuickBooks sync job failed permanently', [ // @pest-mutate-ignore approved sync failure observability
            'time_entry_id' => $this->timeEntryId, // @pest-mutate-ignore approved sync failure observability
            'employee_id' => $this->employeeId, // @pest-mutate-ignore approved sync failure observability
            'group_key' => $this->groupKey, // @pest-mutate-ignore approved sync failure observability
            'exception' => $exception->getMessage(), // @pest-mutate-ignore approved sync failure observability
        ]);
    }
}
