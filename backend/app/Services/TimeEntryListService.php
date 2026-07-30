<?php

/**
 * Unified employee time entry list combining local rows and legacy QuickBooks snapshots.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\TimeEntryApiResponse;
use Illuminate\Support\Collection;

/**
 * Merges pending local entries with QBO-only activities shown as approved.
 */
class TimeEntryListService
{
    /**
     * Injects employee authorization, snapshot queries, and optional reconcile.
     *
     * @param  QboEmployeeAuthorizationService  $employeeAuthorization  QBO employee ownership checks.
     * @param  TimeActivitySnapshotService  $snapshots  Local snapshot persistence.
     * @param  TimeActivitySyncService  $sync  Realm-wide reconcile from QuickBooks.
     */
    public function __construct(
        private readonly QboEmployeeAuthorizationService $employeeAuthorization,
        private readonly TimeActivitySnapshotService $snapshots,
        private readonly TimeActivitySyncService $sync,
    ) {}

    /**
     * Lists merged time entries for the authenticated employee.
     *
     * @param  User  $user  Employee whose entries are listed.
     * @param  QuickBooksToken|null  $token  Organization QuickBooks token when available.
     * @param  int  $startPosition  One-based pagination offset.
     * @param  int  $maxResults  Maximum rows per page.
     * @param  bool  $refresh  When true, reconciles QuickBooks snapshots before reading.
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|bool>}
     */
    public function listForUser(
        User $user,
        ?QuickBooksToken $token,
        int $startPosition,
        int $maxResults,
        bool $refresh = false,
    ): array {
        $startPosition = max(1, $startPosition);
        $maxResults = max(1, $maxResults);
        $offset = max(0, $startPosition - 1);
        $fetchLimit = $offset + $maxResults;

        $linkedQboIds = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereNotNull('qbo_id')
            ->pluck('qbo_id')
            ->filter(fn (?string $qboId): bool => $qboId !== null && $qboId !== '')
            ->values()
            ->all();

        $localTotal = TimeEntry::query()->where('user_id', $user->id)->count();
        $snapshotTotal = $this->legacySnapshotCountForUser($user, $token, $linkedQboIds, $refresh);
        $total = $localTotal + $snapshotTotal;

        $localEntries = TimeEntry::query()
            ->with(['user', 'reviewedBy'])
            ->where('user_id', $user->id)
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->limit($fetchLimit)
            ->get();

        $legacySnapshots = $this->legacySnapshotsForUser(
            $user,
            $token,
            $linkedQboIds,
            $refresh,
            $fetchLimit,
        );

        $merged = $localEntries
            ->map(fn (TimeEntry $entry): array => TimeEntryApiResponse::resource($entry))
            ->concat($legacySnapshots->map(fn (TimeActivitySnapshot $snapshot): array => TimeEntryApiResponse::fromSnapshot($snapshot)))
            ->sortByDesc(fn (array $row): string => $this->sortKey($row))
            ->values();

        $page = $merged->slice($offset, $maxResults)->values();
        $count = $page->count();

        return [
            'data' => $page->all(),
            'meta' => [
                'count' => $count,
                'max_results' => $maxResults,
                'start_position' => $startPosition,
                'truncated' => $offset + $count < $total,
            ],
        ];
    }

    /**
     * Counts legacy QuickBooks snapshots not already linked to local rows.
     *
     * @param  User  $user  Employee whose legacy activities are listed.
     * @param  QuickBooksToken|null  $token  Organization QuickBooks token when available.
     * @param  list<string>  $linkedQboIds  QuickBooks ids already linked to local rows.
     * @param  bool  $refresh  When true, reconciles from QuickBooks before reading snapshots.
     * @return int
     */
    private function legacySnapshotCountForUser(
        User $user,
        ?QuickBooksToken $token,
        array $linkedQboIds,
        bool $refresh,
    ): int {
        $context = $this->legacySnapshotContext($user, $token, $refresh);

        if ($context === null) {
            return 0;
        }

        return TimeActivitySnapshot::query()
            ->where('realm_id', $context['realm_id'])
            ->where('qbo_employee_ref', $context['employee_ref'])
            ->when($linkedQboIds !== [], fn ($query) => $query->whereNotIn('qbo_id', $linkedQboIds))
            ->count();
    }

    /**
     * Loads QuickBooks snapshots that are not already represented by local entries.
     *
     * @param  User  $user  Employee whose legacy activities are listed.
     * @param  QuickBooksToken|null  $token  Organization QuickBooks token when available.
     * @param  list<string>  $linkedQboIds  QuickBooks ids already linked to local rows.
     * @param  bool  $refresh  When true, reconciles from QuickBooks before reading snapshots.
     * @param  int|null  $limit  Maximum rows to fetch before merging.
     * @return Collection<int, TimeActivitySnapshot>
     */
    private function legacySnapshotsForUser(
        User $user,
        ?QuickBooksToken $token,
        array $linkedQboIds,
        bool $refresh,
        ?int $limit = null,
    ): Collection {
        $context = $this->legacySnapshotContext($user, $token, $refresh);

        if ($context === null) {
            return collect();
        }

        $query = TimeActivitySnapshot::query()
            ->where('realm_id', $context['realm_id'])
            ->where('qbo_employee_ref', $context['employee_ref'])
            ->when($linkedQboIds !== [], fn ($builder) => $builder->whereNotIn('qbo_id', $linkedQboIds))
            ->orderByDesc('start_time')
            ->orderByDesc('end_time')
            ->orderByDesc('txn_date')
            ->orderByDesc('qbo_id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Resolves realm and employee context for legacy snapshot queries.
     *
     * @param  User  $user  Employee whose legacy activities are listed.
     * @param  QuickBooksToken|null  $token  Organization QuickBooks token when available.
     * @param  bool  $refresh  When true, reconciles from QuickBooks before reading snapshots.
     * @return array{realm_id: string, employee_ref: string}|null
     */
    private function legacySnapshotContext(User $user, ?QuickBooksToken $token, bool $refresh): ?array
    {
        if ($token === null) {
            return null;
        }

        try {
            $employeeRef = $this->employeeAuthorization->resolveEmployeeRef($user);
        } catch (\Throwable) {
            return null;
        }

        $realmId = $token->realm_id;

        if ($refresh || ! $this->snapshots->realmHasSnapshots($realmId)) {
            $this->sync->reconcileRealm($token);
        }

        return [
            'realm_id' => $realmId,
            'employee_ref' => $employeeRef,
        ];
    }

    /**
     * Builds a descending sort key for merged list rows.
     *
     * @param  array<string, mixed>  $row  Unified list payload.
     * @return string
     */
    private function sortKey(array $row): string
    {
        $start = $row['start_time'] ?? null;
        $created = $row['created_at'] ?? null;
        $listId = $row['list_id'] ?? '';

        return (is_string($start) && $start !== '' ? $start : (is_string($created) ? $created : ''))
            .':'
            .$listId;
    }
}
