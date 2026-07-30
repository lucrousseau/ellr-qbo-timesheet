<?php

/**
 * Supervisor approval workflow and QuickBooks sync for local time entries.
 */

namespace App\Services;

use App\Enums\TimeEntryStatus;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\TimeEntryApiResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Lists pending entries for reviewers and pushes approved rows to QuickBooks.
 */
class TimeEntryApprovalService
{
    /**
     * Injects authorization, QuickBooks writes, and token resolution.
     *
     * @param  TimeEntryAuthorizationService  $authorization  Review permission checks.
     * @param  QboPickerValidationService  $pickerValidation  QuickBooks picker reference validator.
     * @param  TimeActivityService  $timeActivities  QuickBooks time activity writer.
     * @param  QuickBooksTokenResolverService  $tokenResolver  Resolves organization QBO token.
     */
    public function __construct(
        private readonly TimeEntryAuthorizationService $authorization,
        private readonly QboPickerValidationService $pickerValidation,
        private readonly TimeActivityService $timeActivities,
        private readonly QuickBooksTokenResolverService $tokenResolver,
    ) {}

    /**
     * Lists pending time entries the actor may review.
     *
     * @param  User  $actor  Supervisor or administrator.
     * @param  int  $startPosition  One-based pagination offset.
     * @param  int  $maxResults  Maximum rows per page.
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|bool>}
     */
    public function listPendingForReviewer(User $actor, int $startPosition, int $maxResults): array
    {
        $query = TimeEntry::query()
            ->with(['user', 'reviewedBy'])
            ->where('organization_id', $actor->organization_id)
            ->where('status', TimeEntryStatus::Pending)
            ->when(! $actor->isAdmin(), function ($builder) use ($actor): void {
                $builder->whereHas('user', fn ($userQuery) => $userQuery->where('supervisor_id', $actor->id));
            })
            ->orderBy('start_time')
            ->orderBy('id');

        $total = (clone $query)->count();
        $offset = max(0, $startPosition - 1);
        $entries = $query->offset($offset)->limit($maxResults)->get();
        $count = $entries->count();

        return [
            'data' => TimeEntryApiResponse::collection($entries),
            'meta' => [
                'count' => $count,
                'max_results' => $maxResults,
                'start_position' => $startPosition,
                'truncated' => $offset + $count < $total,
            ],
        ];
    }

    /**
     * Approves a pending entry and synchronizes it to QuickBooks.
     *
     * @param  User  $actor  Supervisor or administrator.
     * @param  int  $id  Local time entry identifier.
     * @return TimeEntry
     */
    public function approve(User $actor, int $id): TimeEntry
    {
        [$entry, $employee, $token, $payload] = DB::transaction(function () use ($actor, $id): array {
            $entry = $this->findPendingEntry($id, lock: true);
            $this->authorization->assertCanReview($actor, $entry);

            $employee = $entry->user;
            $token = $this->tokenResolver->resolve($actor);

            if ($this->entryHasPickerValues($entry)) {
                $this->pickerValidation->assertValidTimeEntry($employee, $token, $entry);
            }

            $entry->fill([
                'status' => TimeEntryStatus::Approved,
                'reviewed_by_id' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            return [
                $entry->refresh()->load(['user', 'reviewedBy']),
                $employee,
                $token,
                $this->toQboPayload($entry),
            ];
        });

        try {
            $qboActivity = $this->timeActivities->createForUser($employee, $token, $payload);
        } catch (Throwable $exception) {
            $this->revertApproval($entry);

            throw $exception;
        }

        TimeEntry::query()
            ->whereKey($entry->id)
            ->where('status', TimeEntryStatus::Approved)
            ->whereNull('qbo_id')
            ->update(['qbo_id' => (string) $qboActivity->Id]);

        return $entry->refresh()->load(['user', 'reviewedBy']);
    }

    /**
     * Rejects a pending entry without synchronizing it to QuickBooks.
     *
     * @param  User  $actor  Supervisor or administrator.
     * @param  int  $id  Local time entry identifier.
     * @param  string|null  $reason  Optional rejection reason for the employee.
     * @return TimeEntry
     */
    public function reject(User $actor, int $id, ?string $reason): TimeEntry
    {
        return DB::transaction(function () use ($actor, $id, $reason): TimeEntry {
            $entry = $this->findPendingEntry($id, lock: true);
            $this->authorization->assertCanReview($actor, $entry);

            $entry->fill([
                'status' => TimeEntryStatus::Rejected,
                'reviewed_by_id' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            return $entry->refresh()->load(['user', 'reviewedBy']);
        });
    }

    /**
     * Loads a pending entry by identifier.
     *
     * @param  int  $id  Local time entry identifier.
     * @param  bool  $lock  When true, locks the row for update.
     * @return TimeEntry
     */
    private function findPendingEntry(int $id, bool $lock = false): TimeEntry
    {
        $query = TimeEntry::query()
            ->with('user')
            ->whereKey($id)
            ->where('status', TimeEntryStatus::Pending);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOr(function (): never {
            abort(response()->json(['message' => __('api.time_entry_not_found')], 404));
        });
    }

    /**
     * Restores a pending entry after a failed QuickBooks synchronization.
     *
     * @param  TimeEntry  $entry  Entry that was marked approved before QBO failed.
     * @return void
     */
    private function revertApproval(TimeEntry $entry): void
    {
        TimeEntry::query()
            ->whereKey($entry->id)
            ->where('status', TimeEntryStatus::Approved)
            ->whereNull('qbo_id')
            ->update([
                'status' => TimeEntryStatus::Pending,
                'reviewed_by_id' => null,
                'reviewed_at' => null,
            ]);
    }

    /**
     * Maps a local entry to the QuickBooks create payload shape.
     *
     * @param  TimeEntry  $entry  Approved local entry.
     * @return array<string, mixed>
     */
    private function toQboPayload(TimeEntry $entry): array
    {
        return [
            'customer_ref' => $entry->customer_ref,
            'customer_name' => $entry->customer_name,
            'project_ref' => $entry->project_ref,
            'project_name' => $entry->project_name,
            'item_ref' => $entry->item_ref,
            'item_name' => $entry->item_name,
            'start_time' => $entry->start_time?->toIso8601String(),
            'end_time' => $entry->end_time?->toIso8601String(),
            'description' => $entry->description,
            'is_billable' => $entry->is_billable,
        ];
    }

    /**
     * Indicates whether the entry includes QuickBooks picker references to validate.
     *
     * @param  TimeEntry  $entry  Local time entry being reviewed.
     * @return bool
     */
    private function entryHasPickerValues(TimeEntry $entry): bool
    {
        foreach ([$entry->customer_ref, $entry->project_ref, $entry->item_ref] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
