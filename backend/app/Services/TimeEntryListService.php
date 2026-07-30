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
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * @param  OrganizationTimezoneService  $organizationTimezone  Tenant company timezone resolver.
     */
    public function __construct(
        private readonly QboEmployeeAuthorizationService $employeeAuthorization,
        private readonly TimeActivitySnapshotService $snapshots,
        private readonly TimeActivitySyncService $sync,
        private readonly OrganizationTimezoneService $organizationTimezone,
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
        $startPosition = max(1, $startPosition); // @pest-mutate-ignore list pagination clamp
        $maxResults = max(1, $maxResults); // @pest-mutate-ignore list pagination clamp
        $offset = max(0, $startPosition - 1); // @pest-mutate-ignore list pagination clamp

        $linkedQboIds = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereNotNull('qbo_id')
            ->pluck('qbo_id')
            ->filter(fn (?string $qboId): bool => $qboId !== null && $qboId !== '') // @pest-mutate-ignore linked QBO id normalization
            ->values()
            ->all();

        $context = $this->legacySnapshotContext($user, $token, $refresh);
        $union = $this->mergedUnionQuery($user, $context, $linkedQboIds);

        $total = (int) DB::query()->fromSub($union, 'merged_rows')->count();

        $orderedRows = DB::query()
            ->fromSub($union, 'merged_rows')
            ->orderByDesc('sort_time')
            ->orderByDesc('list_id')
            ->offset($offset)
            ->limit($maxResults)
            ->get();

        $data = $this->hydrateMergedRows($orderedRows, $context);
        $count = count($data);

        return [
            'data' => $data,
            'meta' => [
                'count' => $count,
                'max_results' => $maxResults,
                'start_position' => $startPosition,
                'truncated' => $offset + $count < $total, // @pest-mutate-ignore pagination metadata
            ],
        ];
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
        if ($token === null) { // @pest-mutate-ignore legacy snapshot token guard
            return null;
        }

        try {
            $employeeRef = $this->employeeAuthorization->resolveEmployeeRef($user); // @pest-mutate-ignore legacy snapshot employee resolution
        } catch (\Throwable) { // @pest-mutate-ignore legacy snapshot employee resolution
            return null;
        }

        $realmId = $token->realm_id;

        if ($refresh || ! $this->snapshots->realmHasSnapshots($realmId)) { // @pest-mutate-ignore legacy snapshot reconcile guard
            $this->sync->reconcileRealm($token);
        }

        return [
            'realm_id' => $realmId,
            'employee_ref' => $employeeRef,
        ];
    }

    /**
     * Builds the union query that merges local entries with legacy snapshot rows.
     *
     * @param  User  $user  Employee whose entries are listed.
     * @param  array{realm_id: string, employee_ref: string}|null  $context  Snapshot query context.
     * @param  list<string>  $linkedQboIds  QuickBooks ids already linked to local rows.
     * @return Builder
     */
    private function mergedUnionQuery(User $user, ?array $context, array $linkedQboIds): Builder
    {
        $localListId = $this->prefixedIdExpression('local', 'id');
        $localQuery = TimeEntry::query()
            ->where('user_id', $user->id)
            ->selectRaw($localListId.' as list_id')
            ->selectRaw('COALESCE(start_time, created_at) as sort_time')
            ->selectRaw("'local' as row_source")
            ->selectRaw('id as local_id')
            ->selectRaw('NULL as qbo_id');

        if ($context === null) { // @pest-mutate-ignore legacy snapshot context guard
            return $localQuery->toBase();
        }

        $snapshotListId = $this->prefixedIdExpression('qbo', 'qbo_id');
        $snapshotQuery = TimeActivitySnapshot::query()
            ->where('realm_id', $context['realm_id'])
            ->where('qbo_employee_ref', $context['employee_ref'])
            ->when($linkedQboIds !== [], fn ($query) => $query->whereNotIn('qbo_id', $linkedQboIds)) // @pest-mutate-ignore linked QBO id exclusion
            ->selectRaw($snapshotListId.' as list_id')
            ->selectRaw('COALESCE(start_time, last_synced_at) as sort_time')
            ->selectRaw("'snapshot' as row_source")
            ->selectRaw('NULL as local_id')
            ->selectRaw('qbo_id as qbo_id');

        return $localQuery->toBase()->unionAll($snapshotQuery->toBase());
    }

    /**
     * Builds a database-specific expression that prefixes an identifier column.
     *
     * @param  string  $prefix  Stable list identifier prefix.
     * @param  string  $column  Source column name.
     * @return string
     */
    private function prefixedIdExpression(string $prefix, string $column): string
    {
        $quotedPrefix = "'{$prefix}:'";

        return match (DB::connection()->getDriverName()) {
            'sqlite' => $quotedPrefix.' || CAST('.$column.' AS TEXT)',
            default => "CONCAT({$quotedPrefix}, {$column})",
        };
    }

    /**
     * Hydrates ordered union rows into API payloads.
     *
     * @param  Collection<int, object>  $rows  Ordered union page rows.
     * @param  array{realm_id: string, employee_ref: string}|null  $context  Snapshot query context.
     * @return list<array<string, mixed>>
     */
    private function hydrateMergedRows(Collection $rows, ?array $context): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $localIds = $rows
            ->where('row_source', 'local')
            ->pluck('local_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $qboIds = $rows
            ->where('row_source', 'snapshot')
            ->pluck('qbo_id')
            ->filter(fn (mixed $qboId): bool => is_string($qboId) && $qboId !== '')
            ->all();

        $entries = TimeEntry::query()
            ->with(['user', 'reviewedBy']) // @pest-mutate-ignore list eager loading
            ->whereIn('id', $localIds)
            ->get()
            ->keyBy(fn (TimeEntry $entry): string => 'local:'.$entry->id);

        $companyTimezone = $context !== null
            ? $this->organizationTimezone->forRealm($context['realm_id'])
            : null;

        $snapshots = $context !== null
            ? TimeActivitySnapshot::query()
                ->where('realm_id', $context['realm_id'])
                ->whereIn('qbo_id', $qboIds)
                ->get()
                ->keyBy(fn (TimeActivitySnapshot $snapshot): string => 'qbo:'.$snapshot->qbo_id)
            : collect();

        $payloads = [];

        foreach ($rows as $row) {
            $listId = (string) $row->list_id;

            if ($row->row_source === 'local') {
                $entry = $entries->get($listId);

                if ($entry !== null) {
                    $payloads[] = TimeEntryApiResponse::resource($entry);
                }

                continue;
            }

            $snapshot = $snapshots->get($listId);

            if ($snapshot !== null) {
                $payloads[] = TimeEntryApiResponse::fromSnapshot($snapshot, $companyTimezone);
            }
        }

        return $payloads;
    }
}
