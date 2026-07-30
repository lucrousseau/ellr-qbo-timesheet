<?php

/**
 * Validates QuickBooks picker references against allowed list endpoints.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\QboRefNormalizer;

/**
 * Ensures timer session selections reference allowed QuickBooks customers, projects, and services.
 */
class QboPickerValidationService
{
    /**
     * Injects employee-scoped customer and project list services.
     *
     * @param  QboCustomerListService  $customers  Employee customer list service.
     * @param  QboProjectListService  $projects  Customer-scoped project list service.
     * @param  QboServiceListService  $services  Active service item list service.
     */
    public function __construct(
        private readonly QboCustomerListService $customers,
        private readonly QboProjectListService $projects,
        private readonly QboServiceListService $services,
    ) {}

    /**
     * Aborts with 422 when any provided picker reference is not allowed.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  array<string, mixed>  $validated  Validated timer payload.
     * @return void
     */
    public function assertValidSelections(User $user, QuickBooksToken $token, array $validated): void
    {
        $sanitized = $this->sanitizeSessionSelections($user, $token, $validated);

        if (! QboRefNormalizer::refsMatch(
            $this->normalizedRef($validated['customer_ref'] ?? null),
            $sanitized['customer_ref'],
        )) {
            abort(response()->json(['message' => __('api.time_tracker_invalid_customer')], 422));
        }

        if (! QboRefNormalizer::refsMatch(
            $this->normalizedRef($validated['project_ref'] ?? null),
            $sanitized['project_ref'],
        )) {
            abort(response()->json(['message' => __('api.time_tracker_invalid_project')], 422));
        }

        if (! QboRefNormalizer::refsMatch(
            $this->normalizedRef($validated['service_ref'] ?? null),
            $sanitized['service_ref'],
        )) {
            abort(response()->json(['message' => __('api.time_tracker_invalid_service')], 422));
        }
    }

    /**
     * Aborts with 422 when any time entry picker reference is not allowed.
     *
     * @param  User  $user  Entry owner.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  array<string, mixed>  $validated  Time entry payload fields.
     * @return void
     */
    public function assertValidTimeEntrySelections(User $user, QuickBooksToken $token, array $validated): void
    {
        $this->assertValidSelections($user, $token, [
            'customer_ref' => $validated['customer_ref'] ?? null,
            'project_ref' => $validated['project_ref'] ?? null,
            'service_ref' => $validated['item_ref'] ?? null,
        ]);
    }

    /**
     * Aborts with 422 when a stored time entry references disallowed QuickBooks pickers.
     *
     * @param  User  $user  Entry owner.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  TimeEntry  $entry  Local time entry being reviewed.
     * @return void
     */
    public function assertValidTimeEntry(User $user, QuickBooksToken $token, TimeEntry $entry): void
    {
        $this->assertValidTimeEntrySelections($user, $token, [
            'customer_ref' => $entry->customer_ref,
            'project_ref' => $entry->project_ref,
            'item_ref' => $entry->item_ref,
        ]);
    }

    /**
     * Clears picker references that are no longer allowed for the user.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  array<string, mixed>  $selections  Stored timer picker fields.
     * @return array{
     *     customer_ref: string|null,
     *     customer_name: string|null,
     *     project_ref: string|null,
     *     project_name: string|null,
     *     service_ref: string|null,
     *     service_name: string|null
     * }
     */
    public function sanitizeSessionSelections(User $user, QuickBooksToken $token, array $selections): array
    {
        $sanitized = [
            'customer_ref' => $this->nullableString($selections['customer_ref'] ?? null),
            'customer_name' => $this->nullableString($selections['customer_name'] ?? null),
            'project_ref' => $this->nullableString($selections['project_ref'] ?? null),
            'project_name' => $this->nullableString($selections['project_name'] ?? null),
            'service_ref' => $this->nullableString($selections['service_ref'] ?? null),
            'service_name' => $this->nullableString($selections['service_name'] ?? null),
        ];

        $customerRef = QboRefNormalizer::normalize($sanitized['customer_ref']);

        if ($customerRef !== null && ! QboRefNormalizer::optionExists(
            $this->customers->listForUser($user, $token),
            $customerRef,
        )) {
            return $this->withoutCustomerAndProject($sanitized);
        }

        $sanitized['customer_ref'] = $customerRef;

        $projectRef = QboRefNormalizer::normalize($sanitized['project_ref']);

        if ($projectRef !== null) {
            if ($customerRef === null) {
                $sanitized['project_ref'] = null;
                $sanitized['project_name'] = null;
            } elseif (! QboRefNormalizer::optionExists(
                $this->projects->listForCustomer($token, $customerRef),
                $projectRef,
            )) {
                $sanitized['project_ref'] = null;
                $sanitized['project_name'] = null;
            } else {
                $sanitized['project_ref'] = $projectRef;
            }
        }

        $serviceRef = QboRefNormalizer::normalize($sanitized['service_ref']);

        if ($serviceRef !== null && ! QboRefNormalizer::optionExists(
            $this->services->listActive($token),
            $serviceRef,
        )) {
            $sanitized['service_ref'] = null;
            $sanitized['service_name'] = null;
        } else {
            $sanitized['service_ref'] = $serviceRef;
        }

        return $sanitized;
    }

    /**
     * Normalizes a nullable QuickBooks reference to a non-empty string or null.
     *
     * @param  mixed  $ref  Raw reference value.
     * @return string|null
     */
    private function normalizedRef(mixed $ref): ?string
    {
        if ($ref === null) {
            return null;
        }

        $value = trim((string) $ref);

        return $value !== '' ? $value : null;
    }

    /**
     * Returns a nullable trimmed string for persisted picker labels and refs.
     *
     * @param  mixed  $value  Raw stored value.
     * @return string|null
     */
    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Clears customer and dependent project selections.
     *
     * @param  array<string, string|null>  $selections  Stored timer picker fields.
     * @return array<string, string|null>
     */
    private function withoutCustomerAndProject(array $selections): array
    {
        return [
            ...$selections,
            'customer_ref' => null,
            'customer_name' => null,
            'project_ref' => null,
            'project_name' => null,
        ];
    }
}
