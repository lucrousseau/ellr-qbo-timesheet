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
use App\Support\TimeEntryMergedListQuery;
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
     * @param  TimeActivityReconcileCoordinatorService  $reconcileCoordinator  Reconcile dispatch and inline refresh.
     * @param  OrganizationTimezoneService  $organizationTimezone  Tenant company timezone resolver.
     * @param  TimeEntryPresentationService  $presentation  Read-time label resolution for local rows.
     */
    public function __construct(
        private readonly QboEmployeeAuthorizationService $employeeAuthorization,
        private readonly TimeActivitySnapshotService $snapshots,
        private readonly TimeActivityReconcileCoordinatorService $reconcileCoordinator,
        private readonly OrganizationTimezoneService $organizationTimezone,
        private readonly TimeEntryPresentationService $presentation,
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

        $context = $this->legacySnapshotContext($user, $token, $refresh);
        $total = TimeEntryMergedListQuery::count($user, $context);
        $union = TimeEntryMergedListQuery::unionQuery($user, $context);

        $orderedRows = DB::query()
            ->fromSub($union, 'merged_rows')
            ->orderByDesc('sort_time')
            ->orderByDesc('list_id')
            ->offset($offset)
            ->limit($maxResults)
            ->get();

        $data = $this->hydrateMergedRows($orderedRows, $context, $user, $token);
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
            $this->reconcileCoordinator->prepareRealmForList($token, $refresh);
        }

        return [
            'realm_id' => $realmId,
            'employee_ref' => $employeeRef,
        ];
    }

    /**
     * Hydrates ordered union rows into API payloads.
     *
     * @param  Collection<int, \stdClass>  $rows  Ordered union page rows.
     * @param  array{realm_id: string, employee_ref: string}|null  $context  Snapshot query context.
     * @param  User  $user  Employee whose entries are listed.
     * @param  QuickBooksToken|null  $token  Organization QuickBooks token when available.
     * @return list<array<string, mixed>>
     */
    private function hydrateMergedRows(Collection $rows, ?array $context, User $user, ?QuickBooksToken $token): array
    {
        if ($rows->isEmpty()) { // @pest-mutate-ignore merged list hydration shortcut
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
            ->filter(fn (mixed $qboId): bool => is_string($qboId) && $qboId !== '') // @pest-mutate-ignore linked QBO id normalization
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
            $listId = (string) $row->list_id; // @pest-mutate-ignore merged list identifier normalization

            if ($row->row_source === 'local') {
                $entry = $entries->get($listId);

                if ($entry !== null) {
                    $payloads[] = $this->presentation->resource($entry, $user, $token);
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
