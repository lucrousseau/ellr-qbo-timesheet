<?php

/**
 * Submits draft or rejected local time entries for supervisor approval.
 */

namespace App\Services;

use App\Enums\TimeEntryStatus;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Moves owned time entries from draft or rejected into pending review.
 */
class TimeEntrySubmitService
{
    /**
     * Injects owned entry lookup from the write-model service.
     *
     * @param  TimeEntryService  $timeEntries  Local time entry writer.
     */
    public function __construct(
        private readonly TimeEntryService $timeEntries,
    ) {}

    /**
     * Submits a draft or rejected entry for supervisor approval.
     *
     * @param  User  $user  Entry owner.
     * @param  int  $id  Local time entry identifier.
     * @return TimeEntry
     */
    public function submitForUser(User $user, int $id): TimeEntry
    {
        $entry = $this->timeEntries->findOwnedEntry($user, $id);
        $this->assertSubmittable($entry);

        return $this->markPending($entry);
    }

    /**
     * Submits owned draft or rejected entries for approval.
     *
     * @param  User  $user  Entry owner.
     * @param  list<int>|null  $ids  Optional entry ids; null submits all submittable owned entries.
     * @return Collection<int, TimeEntry>
     */
    public function submitManyForUser(User $user, ?array $ids = null): Collection
    {
        $query = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [TimeEntryStatus::Draft, TimeEntryStatus::Rejected]);

        if ($ids !== null) {
            $query->whereKey($ids);
        }

        $entries = $query->orderBy('id')->get();

        if ($ids !== null && $entries->count() !== count(array_unique($ids))) {
            abort(response()->json([
                'error' => 'time_entry_not_submittable',
                'message' => __('api.time_entry_not_submittable'),
            ], 422));
        }

        return $entries->map(fn (TimeEntry $entry): TimeEntry => $this->markPending($entry));
    }

    /**
     * Marks an entry as pending review and clears prior review metadata.
     *
     * @param  TimeEntry  $entry  Submittable entry.
     * @return TimeEntry
     */
    private function markPending(TimeEntry $entry): TimeEntry
    {
        $entry->forceFill([
            'status' => TimeEntryStatus::Pending,
            'reviewed_by_id' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ])->save();

        return $entry->refresh();
    }

    /**
     * Aborts when the entry cannot be submitted for approval.
     *
     * @param  TimeEntry  $entry  Entry being submitted.
     * @return void
     */
    private function assertSubmittable(TimeEntry $entry): void
    {
        if (! $entry->status->isSubmittable()) {
            abort(response()->json([
                'error' => 'time_entry_not_submittable',
                'message' => __('api.time_entry_not_submittable'),
            ], 422));
        }
    }
}
