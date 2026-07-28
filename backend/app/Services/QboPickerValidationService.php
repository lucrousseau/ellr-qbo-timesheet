<?php

/**
 * Validates QuickBooks picker references against allowed list endpoints.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Models\User;

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
        $customerRef = $this->normalizedRef($validated['customer_ref'] ?? null);

        if ($customerRef !== null && ! $this->optionExists(
            $this->customers->listForUser($user, $token),
            $customerRef,
        )) {
            abort(response()->json(['message' => __('api.time_tracker_invalid_customer')], 422));
        }

        $projectRef = $this->normalizedRef($validated['project_ref'] ?? null);

        if ($projectRef !== null) {
            if ($customerRef === null) {
                abort(response()->json(['message' => __('api.time_tracker_invalid_project')], 422));
            }

            if (! $this->optionExists(
                $this->projects->listForCustomer($token, $customerRef),
                $projectRef,
            )) {
                abort(response()->json(['message' => __('api.time_tracker_invalid_project')], 422));
            }
        }

        $serviceRef = $this->normalizedRef($validated['service_ref'] ?? null);

        if ($serviceRef !== null && ! $this->optionExists(
            $this->services->listActive($token),
            $serviceRef,
        )) {
            abort(response()->json(['message' => __('api.time_tracker_invalid_service')], 422));
        }
    }

    /**
     * Returns whether a picker option list contains the given QuickBooks identifier.
     *
     * @param  array<int, array{id: string, display_name: string}>  $options  Picker rows.
     * @param  string  $ref  QuickBooks entity identifier.
     * @return bool
     */
    private function optionExists(array $options, string $ref): bool
    {
        foreach ($options as $option) {
            if ($option['id'] === $ref) {
                return true;
            }
        }

        return false;
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
}
