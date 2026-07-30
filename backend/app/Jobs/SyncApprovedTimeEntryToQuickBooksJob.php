<?php

/**
 * Queued QuickBooks sync for an approved local time entry.
 */

namespace App\Jobs;

use App\Enums\TimeEntryStatus;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeActivityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates the approved time entry in QuickBooks and stores the returned identifier.
 */
class SyncApprovedTimeEntryToQuickBooksJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $timeEntryId  Local time entry primary key.
     * @param  int  $employeeId  Employee who logged the entry.
     * @param  int  $tokenId  Organization QuickBooks token primary key.
     * @param  array<string, mixed>  $payload  QuickBooks create payload.
     */
    public function __construct(
        public readonly int $timeEntryId,
        public readonly int $employeeId,
        public readonly int $tokenId,
        public readonly array $payload,
    ) {}

    /**
     * Pushes the approved entry to QuickBooks while keeping the approval decision intact.
     *
     * @param  TimeActivityService  $timeActivities  QuickBooks time activity writer.
     * @return void
     */
    public function handle(TimeActivityService $timeActivities): void
    {
        $entry = TimeEntry::query()->find($this->timeEntryId);
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
            $qboActivity = $timeActivities->createForUser($employee, $token, $this->payload);
        } catch (Throwable $exception) {
            Log::warning('QuickBooks synchronization failed for approved time entry', [
                'time_entry_id' => $entry->id,
                'employee_id' => $employee->id,
                'exception' => $exception->getMessage(),
            ]);

            throw new \RuntimeException('QuickBooks synchronization failed for time entry '.$entry->id, 0, $exception);
        }

        TimeEntry::query()
            ->whereKey($entry->id)
            ->where('status', TimeEntryStatus::Approved)
            ->whereNull('qbo_id')
            ->update(['qbo_id' => (string) $qboActivity->Id]);
    }

    /**
     * Logs a terminal queue failure without reverting the approval decision.
     *
     * @param  Throwable  $exception  Queue failure reason.
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Approved time entry QuickBooks sync job failed permanently', [
            'time_entry_id' => $this->timeEntryId,
            'employee_id' => $this->employeeId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
