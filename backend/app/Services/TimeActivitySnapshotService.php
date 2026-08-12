<?php

/**
 * Persists and queries the local QuickBooks time activity read model.
 */

namespace App\Services;

use App\Models\TimeActivitySnapshot;
use App\Support\TimeActivitySnapshotMapper;

/**
 * Upserts, soft-deletes, and lists cached time activity snapshots.
 */
class TimeActivitySnapshotService
{
    /**
     * @param  TimeActivitySnapshotMapper  $mapper  Snapshot attribute and API mappers.
     * @param  TimeActivityDisplayEnricherService  $displayEnricher  Resolves reference display names.
     */
    public function __construct(
        private readonly TimeActivitySnapshotMapper $mapper,
        private readonly TimeActivityDisplayEnricherService $displayEnricher,
    ) {}

    /**
     * Upserts multiple snapshots from QuickBooks time activity entities.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  list<object|array<string, mixed>>  $activities  QuickBooks time activity rows.
     * @param  bool  $resolveMissingNames  When false, skips extra QBO lookups for display names.
     * @return list<TimeActivitySnapshot>
     */
    public function upsertBatchFromQboEntities(
        string $realmId,
        object $dataService,
        array $activities,
        bool $resolveMissingNames = true,
    ): array {
        if ($activities === []) {
            return []; // @pest-mutate-ignore empty batch short-circuit
        }

        $rows = array_map( // @pest-mutate-ignore batch entity shape normalize
            fn (object|array $activity): object => is_object($activity) ? $activity : (object) $activity, // @pest-mutate-ignore
            $activities,
        );

        if ($resolveMissingNames) { // @pest-mutate-ignore optional name enrichment toggle
            $rows = $this->displayEnricher->enrich($dataService, $rows);
        }

        $snapshots = [];

        foreach ($rows as $activity) {
            $snapshots[] = $this->upsertFromQboEntity($realmId, $dataService, $activity, false); // @pest-mutate-ignore nested enrich disabled in batch
        }

        return $snapshots;
    }

    /**
     * Upserts a snapshot from a QuickBooks time activity entity.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  object|array<string, mixed>  $activity  QuickBooks time activity row.
     * @param  bool  $resolveMissingNames  When false, skips extra QBO lookups for display names.
     * @return TimeActivitySnapshot
     */
    public function upsertFromQboEntity(
        string $realmId,
        object $dataService,
        object|array $activity,
        bool $resolveMissingNames = true,
    ): TimeActivitySnapshot {
        if ($resolveMissingNames) {
            $enriched = $this->displayEnricher->enrich($dataService, [is_object($activity) ? $activity : (object) $activity]); // @pest-mutate-ignore normalize array shaped QBO rows
            $row = $enriched[0] ?? $activity;
        } else {
            $row = is_object($activity) ? $activity : (object) $activity; // @pest-mutate-ignore
        }

        $attributes = $this->mapper->toAttributes($realmId, $row);
        $attributes = $this->mapper->mergeEnrichedNames($attributes, $row);

        if (! $resolveMissingNames) {
            $attributes = $this->preserveExistingDisplayNames(
                $realmId,
                (string) $attributes['qbo_id'], // @pest-mutate-ignore snapshot id normalization
                $attributes,
            );
        }

        return TimeActivitySnapshot::withTrashed()->updateOrCreate(
            [
                'realm_id' => $realmId,
                'qbo_id' => $attributes['qbo_id'],
            ],
            [
                ...$attributes,
                'deleted_at' => null,
            ],
        );
    }

    /**
     * Soft-deletes a snapshot by QuickBooks identifier.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  string  $qboId  QuickBooks time activity identifier.
     * @return void
     */
    public function softDeleteByQboId(string $realmId, string $qboId): void
    {
        TimeActivitySnapshot::query()
            ->where('realm_id', $realmId)
            ->where('qbo_id', $qboId)
            ->delete();
    }

    /**
     * Soft-deletes snapshots in a reconcile window that no longer exist in QuickBooks.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  string  $minTxnDate  Inclusive lower bound (YYYY-MM-DD) for purge eligibility.
     * @param  list<string>  $seenQboIds  QuickBooks identifiers returned by the latest reconcile scan.
     * @return int Number of rows soft-deleted.
     */
    public function purgeStaleInLookback(string $realmId, string $minTxnDate, array $seenQboIds): int
    {
        if ($seenQboIds === []) {
            return 0;
        }

        return TimeActivitySnapshot::query()
            ->where('realm_id', $realmId)
            ->where(function ($builder) use ($minTxnDate): void {
                $builder->where('txn_date', '>=', $minTxnDate)
                    ->orWhereNull('txn_date');
            })
            ->whereNotIn('qbo_id', $seenQboIds)
            ->delete();
    }

    /**
     * Returns whether a realm has any active snapshot rows.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @return bool
     */
    public function realmHasSnapshots(string $realmId): bool
    {
        return TimeActivitySnapshot::query()
            ->where('realm_id', $realmId)
            ->exists();
    }

    /**
     * Lists snapshots for one employee ordered by most recent work time.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  string  $employeeRef  QuickBooks employee reference.
     * @param  int  $startPosition  Application page start (1-based).
     * @param  int  $maxResults  Maximum rows to return.
     * @return array{0: array<int, object>, 1: int}
     */
    public function listApiObjectsForEmployee(
        string $realmId,
        string $employeeRef,
        int $startPosition,
        int $maxResults,
    ): array {
        $query = TimeActivitySnapshot::query()
            ->where('realm_id', $realmId)
            ->where('qbo_employee_ref', $employeeRef);

        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('start_time')
            ->orderByDesc('end_time')
            ->orderByDesc('txn_date')
            ->orderByDesc('qbo_id')
            ->offset(max(0, $startPosition - 1))
            ->limit($maxResults)
            ->get();

        $objects = $rows
            ->map(fn (TimeActivitySnapshot $snapshot): object => $this->mapper->toApiObject($snapshot))
            ->values()
            ->all();

        return [$objects, $total];
    }

    /**
     * Returns a QuickBooks-shaped object for one snapshot when it exists.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  string  $qboId  QuickBooks time activity identifier.
     * @return object|null
     */
    public function findApiObject(string $realmId, string $qboId): ?object
    {
        $snapshot = TimeActivitySnapshot::query()
            ->where('realm_id', $realmId)
            ->where('qbo_id', $qboId)
            ->first();

        return $snapshot ? $this->mapper->toApiObject($snapshot) : null;
    }

    /**
     * Keeps cached display names when a lightweight upsert omits them.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  string  $qboId  QuickBooks time activity identifier.
     * @param  array<string, mixed>  $attributes  Snapshot attributes.
     * @return array<string, mixed>
     */
    private function preserveExistingDisplayNames(string $realmId, string $qboId, array $attributes): array
    {
        $existing = TimeActivitySnapshot::withTrashed()
            ->where('realm_id', $realmId)
            ->where('qbo_id', $qboId)
            ->first();

        if ($existing === null) {
            return $attributes;
        }

        foreach (['customer_name', 'project_name', 'item_name'] as $field) {
            $value = $attributes[$field] ?? null; // @pest-mutate-ignore preserve cached display names

            if (($value === null || $value === '') && $existing->{$field} !== null && $existing->{$field} !== '') { // @pest-mutate-ignore
                $attributes[$field] = $existing->{$field};
            }
        }

        return $attributes;
    }
}
