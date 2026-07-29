<?php

/**
 * Maps QuickBooks time activity entities to snapshot attributes and API payloads.
 */

namespace App\Support;

use App\Models\TimeActivitySnapshot;

/**
 * Normalizes QuickBooks TimeActivity rows for persistence and JSON responses.
 */
final class TimeActivitySnapshotMapper
{
    /**
     * @param  QboCustomerResolver  $customerResolver  Reference extraction helpers.
     */
    public function __construct(
        private readonly QboCustomerResolver $customerResolver,
    ) {}

    /**
     * Maps a QuickBooks entity into snapshot attributes.
     *
     * @param  string  $realmId  QuickBooks company realm identifier.
     * @param  object|array<string, mixed>  $activity  QuickBooks time activity row.
     * @return array<string, mixed>
     */
    public function toAttributes(string $realmId, object|array $activity): array
    {
        $employeeRef = $this->customerResolver->activityRef($activity, 'EmployeeRef');

        if ($employeeRef === null || $employeeRef === '') {
            throw new \InvalidArgumentException('Time activity is missing EmployeeRef.');
        }

        $qboId = $this->readScalar($activity, 'Id');

        if ($qboId === null || $qboId === '') {
            throw new \InvalidArgumentException('Time activity is missing Id.');
        }

        return [
            'realm_id' => $realmId,
            'qbo_id' => $qboId,
            'qbo_employee_ref' => $employeeRef,
            'customer_ref' => $this->customerResolver->activityRef($activity, 'CustomerRef'),
            'customer_name' => $this->readRefName($activity, 'CustomerRef'),
            'project_ref' => $this->customerResolver->activityRef($activity, 'ProjectRef'),
            'project_name' => $this->readRefName($activity, 'ProjectRef'),
            'item_ref' => $this->customerResolver->activityRef($activity, 'ItemRef'),
            'item_name' => $this->readRefName($activity, 'ItemRef'),
            'start_time' => $this->readDateTime($activity, 'StartTime'),
            'end_time' => $this->readDateTime($activity, 'EndTime'),
            'txn_date' => $this->readDate($activity, 'TxnDate'),
            'description' => $this->readScalar($activity, 'Description'),
            'billable_status' => $this->readScalar($activity, 'BillableStatus'),
            'qbo_sync_token' => $this->readScalar($activity, 'SyncToken'),
            'qbo_last_updated_at' => $this->readMetaLastUpdated($activity),
            'last_synced_at' => now(),
        ];
    }

    /**
     * Applies enriched reference names onto snapshot attributes.
     *
     * @param  array<string, mixed>  $attributes  Snapshot attributes.
     * @param  object|array<string, mixed>  $activity  Enriched QuickBooks row.
     * @return array<string, mixed>
     */
    public function mergeEnrichedNames(array $attributes, object|array $activity): array
    {
        foreach (['CustomerRef' => 'customer', 'ProjectRef' => 'project', 'ItemRef' => 'item'] as $field => $prefix) {
            $name = $this->readRefName($activity, $field);

            if ($name !== null) {
                $attributes["{$prefix}_name"] = $name;
            }
        }

        return $attributes;
    }

    /**
     * Builds a QuickBooks-shaped object for API list responses.
     *
     * @param  TimeActivitySnapshot  $snapshot  Persisted snapshot row.
     * @return object
     */
    public function toApiObject(TimeActivitySnapshot $snapshot): object
    {
        $payload = (object) [
            'Id' => $snapshot->qbo_id,
            'EmployeeRef' => (object) ['value' => $snapshot->qbo_employee_ref],
            'StartTime' => $snapshot->start_time?->toIso8601String(),
            'EndTime' => $snapshot->end_time?->toIso8601String(),
            'TxnDate' => $snapshot->txn_date?->toDateString(),
            'Description' => $snapshot->description,
            'BillableStatus' => $snapshot->billable_status,
        ];

        $payload->CustomerRef = $this->refObject($snapshot->customer_ref, $snapshot->customer_name);
        $payload->ProjectRef = $this->refObject($snapshot->project_ref, $snapshot->project_name);
        $payload->ItemRef = $this->refObject($snapshot->item_ref, $snapshot->item_name);

        if ($snapshot->start_time !== null && $snapshot->end_time !== null) {
            $seconds = max(0, $snapshot->end_time->getTimestamp() - $snapshot->start_time->getTimestamp());
            $payload->Hours = intdiv($seconds, 3600);
            $payload->Minutes = intdiv($seconds % 3600, 60);
        }

        return $payload;
    }

    /**
     * @param  object|array<string, mixed>  $activity  QuickBooks row.
     * @param  string  $field  Field name.
     * @return string|null
     */
    private function readScalar(object|array $activity, string $field): ?string
    {
        $value = is_array($activity) ? ($activity[$field] ?? null) : ($activity->{$field} ?? null);

        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  object|array<string, mixed>  $activity  QuickBooks row.
     * @param  string  $field  Reference field name.
     * @return string|null
     */
    private function readRefName(object|array $activity, string $field): ?string
    {
        $reference = is_array($activity) ? ($activity[$field] ?? null) : ($activity->{$field} ?? null);

        if (is_object($reference) && isset($reference->name)) {
            $name = trim((string) $reference->name);

            return $name !== '' ? $name : null;
        }

        if (is_array($reference) && isset($reference['name'])) {
            $name = trim((string) $reference['name']);

            return $name !== '' ? $name : null;
        }

        return null;
    }

    /**
     * @param  object|array<string, mixed>  $activity  QuickBooks row.
     * @param  string  $field  Datetime field name.
     * @return string|null
     */
    private function readDateTime(object|array $activity, string $field): ?string
    {
        $value = $this->readScalar($activity, $field);

        if ($value === null) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @param  object|array<string, mixed>  $activity  QuickBooks row.
     * @param  string  $field  Date field name.
     * @return string|null
     */
    private function readDate(object|array $activity, string $field): ?string
    {
        $value = $this->readScalar($activity, $field);

        if ($value === null) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    /**
     * @param  object|array<string, mixed>  $activity  QuickBooks row.
     * @return string|null
     */
    private function readMetaLastUpdated(object|array $activity): ?string
    {
        $meta = is_array($activity) ? ($activity['MetaData'] ?? null) : ($activity->MetaData ?? null);

        if (! is_object($meta) || ! isset($meta->LastUpdatedTime)) {
            return null;
        }

        $value = trim((string) $meta->LastUpdatedTime);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @param  string|null  $value  Reference identifier.
     * @param  string|null  $name  Display name.
     * @return object|null
     */
    private function refObject(?string $value, ?string $name): ?object
    {
        if ($value === null || $value === '') {
            return null;
        }

        $ref = (object) ['value' => $value];

        if ($name !== null && $name !== '') {
            $ref->name = $name;
        }

        return $ref;
    }
}
