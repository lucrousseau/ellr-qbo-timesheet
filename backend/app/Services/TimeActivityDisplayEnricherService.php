<?php

/**
 * Resolves QuickBooks reference identifiers into display names for time activities.
 */

namespace App\Services;

use App\Support\QboCustomerResolver;
use App\Support\TimeActivityReferenceNameLookup;

/**
 * Enriches list responses so CustomerRef, ProjectRef, and ItemRef include names.
 */
class TimeActivityDisplayEnricherService
{
    /**
     * Injects customer resolution and API error formatting.
     *
     * @param  QboCustomerResolver  $customerResolver  QuickBooks customer reference helpers.
     * @param  TimeActivityReferenceNameLookup  $nameLookup  Batched customer and item name queries.
     */
    public function __construct(
        private readonly QboCustomerResolver $customerResolver,
        private readonly TimeActivityReferenceNameLookup $nameLookup,
    ) {}

    /**
     * Adds display names to reference fields on each activity row.
     *
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  array<int, mixed>  $activities  QuickBooks time activity rows.
     * @return array<int, mixed>
     */
    public function enrich(object $dataService, array $activities): array
    {
        if ($activities === []) {
            return $activities;
        }

        $customerRefs = [];
        $projectRefs = [];
        $itemRefs = [];

        foreach ($activities as $activity) {
            if ($this->refNeedsEnrichment($activity, 'CustomerRef')) {
                $ref = $this->customerResolver->activityRef($activity, 'CustomerRef');
                if ($ref !== null) {
                    $customerRefs[] = $ref;
                }
            }

            if ($this->refNeedsEnrichment($activity, 'ProjectRef')) {
                $ref = $this->customerResolver->activityRef($activity, 'ProjectRef');
                if ($ref !== null) {
                    $projectRefs[] = $ref;
                }
            }

            if ($this->refNeedsEnrichment($activity, 'ItemRef')) {
                $ref = $this->customerResolver->activityRef($activity, 'ItemRef');
                if ($ref !== null) {
                    $itemRefs[] = $ref;
                }
            }
        }

        $customerNames = $this->nameLookup->customerNames(
            $dataService,
            [...$customerRefs, ...$projectRefs],
        );
        $itemNames = $this->nameLookup->itemNames($dataService, $itemRefs);

        foreach ($activities as $index => $activity) {
            $this->applyRefName($activities[$index], 'CustomerRef', $customerNames);
            $this->applyRefName($activities[$index], 'ProjectRef', $customerNames);
            $this->applyRefName($activities[$index], 'ItemRef', $itemNames);
        }

        return $activities;
    }

    /**
     * Returns whether a reference field is missing a display name.
     *
     * @param  mixed  $activity  QuickBooks time activity row.
     * @param  string  $field  Reference field name.
     * @return bool
     */
    private function refNeedsEnrichment(mixed $activity, string $field): bool
    {
        if ($this->customerResolver->activityRef($activity, $field) === null) {
            return false;
        }

        $reference = is_array($activity)
            ? ($activity[$field] ?? null)
            : (is_object($activity) ? ($activity->{$field} ?? null) : null);

        if (is_object($reference) && isset($reference->name)) {
            $name = trim((string) $reference->name); // @pest-mutate-ignore optional reference name normalization

            return $name === '';
        }

        if (is_array($reference) && isset($reference['name'])) {
            $name = trim((string) $reference['name']); // @pest-mutate-ignore optional reference name normalization

            return $name === '';
        }

        return true;
    }

    /**
     * Attaches a display name to a reference field when a lookup exists.
     *
     * @param  mixed  $activity  QuickBooks time activity row.
     * @param  string  $field  Reference field name.
     * @param  array<string, string>  $nameMap  Display names keyed by reference id.
     * @return void
     */
    private function applyRefName(mixed &$activity, string $field, array $nameMap): void
    {
        $refValue = $this->customerResolver->activityRef($activity, $field);

        if ($refValue === null) {
            return;
        }

        $name = $nameMap[$refValue] ?? null;

        if ($name === null || ! $this->refNeedsEnrichment($activity, $field)) {
            return;
        }

        $enriched = ['value' => $refValue, 'name' => $name];

        if (is_array($activity)) {
            $activity[$field] = $enriched;

            return;
        }

        if (is_object($activity)) {
            $activity->{$field} = (object) $enriched;
        }
    }
}
