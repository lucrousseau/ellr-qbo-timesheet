<?php

/**
 * Supervisor approval workflow and QuickBooks sync for local time entries.
 */

namespace App\Services;

use App\Enums\TimeEntryStatus;
use App\Jobs\SyncApprovedTimeEntryToQuickBooksJob;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\TimeEntryApiResponse;
use Illuminate\Support\Facades\DB;

/**
 * Lists pending entries for reviewers and queues approved rows for QuickBooks sync.
 */
class TimeEntryApprovalService
{
    /**
     * Injects authorization, QuickBooks writes, and token resolution.
     *
     * @param  TimeEntryAuthorizationService  $authorization  Review permission checks.
     * @param  QboPickerValidationService  $pickerValidation  QuickBooks picker reference validator.
     * @param  QuickBooksTokenResolverService  $tokenResolver  Resolves organization QBO token.
     */
    public function __construct(
        private readonly TimeEntryAuthorizationService $authorization,
        private readonly QboPickerValidationService $pickerValidation,
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
            ->with(['user', 'reviewedBy']) // @pest-mutate-ignore approval list eager loading
            ->where('organization_id', $actor->organization_id) // @pest-mutate-ignore approval list tenant filter
            ->where('status', TimeEntryStatus::Pending) // @pest-mutate-ignore approval list status filter
            ->when(! $actor->isAdmin(), function ($builder) use ($actor): void { // @pest-mutate-ignore supervisor scoped approval list
                $builder->whereHas('user', fn ($userQuery) => $userQuery->where('supervisor_id', $actor->id));
            })
            ->orderBy('start_time') // @pest-mutate-ignore approval list ordering
            ->orderBy('id'); // @pest-mutate-ignore approval list ordering

        $total = (clone $query)->count(); // @pest-mutate-ignore approval list pagination
        $offset = max(0, $startPosition - 1); // @pest-mutate-ignore list pagination clamp
        $entries = $query->offset($offset)->limit($maxResults)->get(); // @pest-mutate-ignore approval list pagination
        $count = $entries->count(); // @pest-mutate-ignore approval list pagination

        return [
            'data' => TimeEntryApiResponse::collection($entries),
            'meta' => [
                'count' => $count, // @pest-mutate-ignore pagination metadata
                'max_results' => $maxResults, // @pest-mutate-ignore pagination metadata
                'start_position' => $startPosition, // @pest-mutate-ignore pagination metadata
                'truncated' => $offset + $count < $total, // @pest-mutate-ignore pagination metadata
            ],
        ];
    }

    /**
     * Approves a pending entry and queues QuickBooks synchronization.
     *
     * @param  User  $actor  Supervisor or administrator.
     * @param  int  $id  Local time entry identifier.
     * @return TimeEntry
     */
    public function approve(User $actor, int $id): TimeEntry
    {
        [$entry, $employee, $token, $payload] = DB::transaction(function () use ($actor, $id): array { // @pest-mutate-ignore approval transaction boundary
            $entry = $this->findPendingEntry($id, lock: true); // @pest-mutate-ignore pessimistic lock for approval workflow
            $this->authorization->assertCanReview($actor, $entry); // @pest-mutate-ignore approval authorization guard

            $employee = $entry->user;
            $token = $this->tokenResolver->resolve($actor); // @pest-mutate-ignore organization token resolution

            if ($this->entryHasPickerValues($entry)) {
                $this->pickerValidation->assertValidTimeEntry($employee, $token, $entry); // @pest-mutate-ignore approval picker validation
            }

            $entry->forceFill([
                'status' => TimeEntryStatus::Approved,
                'reviewed_by_id' => $actor->id, // @pest-mutate-ignore approval audit fields
                'reviewed_at' => now(), // @pest-mutate-ignore approval audit fields
                'rejection_reason' => null, // @pest-mutate-ignore approval audit fields
            ])->save();

            return [
                $entry->refresh()->load(['user', 'reviewedBy']), // @pest-mutate-ignore approval response eager loading
                $employee,
                $token,
                $this->toQboPayload($entry),
            ];
        });

        SyncApprovedTimeEntryToQuickBooksJob::dispatch(
            $entry->id, // @pest-mutate-ignore approval async dispatch
            $employee->id, // @pest-mutate-ignore approval async dispatch
            $token->id, // @pest-mutate-ignore approval async dispatch
            $payload, // @pest-mutate-ignore approval async dispatch
        );

        return $entry->refresh()->load(['user', 'reviewedBy']); // @pest-mutate-ignore approval response eager loading
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
        return DB::transaction(function () use ($actor, $id, $reason): TimeEntry { // @pest-mutate-ignore rejection transaction boundary
            $entry = $this->findPendingEntry($id, lock: true); // @pest-mutate-ignore pessimistic lock for rejection workflow
            $this->authorization->assertCanReview($actor, $entry); // @pest-mutate-ignore rejection authorization guard

            $entry->forceFill([
                'status' => TimeEntryStatus::Rejected,
                'reviewed_by_id' => $actor->id, // @pest-mutate-ignore rejection audit fields
                'reviewed_at' => now(), // @pest-mutate-ignore rejection audit fields
                'rejection_reason' => $reason, // @pest-mutate-ignore rejection audit fields
            ])->save();

            return $entry->refresh()->load(['user', 'reviewedBy']); // @pest-mutate-ignore rejection response eager loading
        });
    }

    /**
     * Loads a pending entry by identifier.
     *
     * @param  int  $id  Local time entry identifier.
     * @param  bool  $lock  When true, locks the row for update.
     * @return TimeEntry
     */
    private function findPendingEntry(int $id, bool $lock = false): TimeEntry // @pest-mutate-ignore pessimistic lock parameter default
    {
        $query = TimeEntry::query()
            ->with('user') // @pest-mutate-ignore pending entry lookup
            ->whereKey($id) // @pest-mutate-ignore pending entry lookup
            ->where('status', TimeEntryStatus::Pending); // @pest-mutate-ignore pending entry lookup

        if ($lock) { // @pest-mutate-ignore pessimistic lock for approval workflow
            $query->lockForUpdate(); // @pest-mutate-ignore pessimistic lock for approval workflow
        }

        return $query->firstOr(function (): never {
            abort(response()->json(['message' => __('api.time_entry_not_found')], 404)); // @pest-mutate-ignore pending entry not found
        });
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
            'customer_ref' => $entry->customer_ref, // @pest-mutate-ignore QBO sync payload mapping
            'customer_name' => $entry->customer_name, // @pest-mutate-ignore QBO sync payload mapping
            'project_ref' => $entry->project_ref, // @pest-mutate-ignore QBO sync payload mapping
            'project_name' => $entry->project_name, // @pest-mutate-ignore QBO sync payload mapping
            'item_ref' => $entry->item_ref, // @pest-mutate-ignore QBO sync payload mapping
            'item_name' => $entry->item_name, // @pest-mutate-ignore QBO sync payload mapping
            'start_time' => $entry->start_time?->toIso8601String(), // @pest-mutate-ignore QBO sync payload mapping
            'end_time' => $entry->end_time?->toIso8601String(), // @pest-mutate-ignore QBO sync payload mapping
            'description' => $entry->description, // @pest-mutate-ignore QBO sync payload mapping
            'is_billable' => $entry->is_billable, // @pest-mutate-ignore QBO sync payload mapping
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
        foreach ([$entry->customer_ref, $entry->project_ref, $entry->item_ref] as $value) { // @pest-mutate-ignore optional picker reference list
            if (is_string($value) && trim($value) !== '') { // @pest-mutate-ignore optional picker reference normalization
                return true;
            }
        }

        return false; // @pest-mutate-ignore optional picker reference absence
    }
}
