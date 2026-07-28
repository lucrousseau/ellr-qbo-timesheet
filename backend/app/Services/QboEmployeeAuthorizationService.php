<?php

/**
 * Authorization helpers for QuickBooks employee ownership checks.
 */

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Models\User;

/**
 * Resolves the linked QBO employee and verifies time activity ownership.
 */
class QboEmployeeAuthorizationService
{
    /**
     * Returns the QBO employee reference or aborts with 403.
     *
     * @param  User  $user  Authenticated application user.
     * @return string
     */
    public function resolveEmployeeRef(User $user): string
    {
        $employeeRef = $user->qbo_employee_ref;

        if ($employeeRef === null || $employeeRef === '') {
            abort(response()->json([
                'message' => __('api.qbo_employee_not_configured'),
                'error' => ApiErrorCode::QboEmployeeNotConfigured->value,
            ], 403));
        }

        return $employeeRef;
    }

    /**
     * Verifies the activity targets the same QBO employee as the user.
     *
     * @param  User  $user  Authenticated application user.
     * @param  object  $activity  QuickBooks time activity object.
     * @return void
     */
    public function assertActivityBelongsToUser(User $user, object $activity): void
    {
        $employeeRef = $this->resolveEmployeeRef($user);
        $activityEmployeeRef = $this->extractEmployeeRef($activity);

        if ($activityEmployeeRef !== $employeeRef) {
            abort(response()->json(['message' => __('api.time_activity_not_found')], 404));
        }
    }

    /**
     * Extracts the employee reference from an SDK activity object.
     *
     * @param  object  $activity  QuickBooks time activity object.
     * @return string|null
     */
    public function extractEmployeeRef(object $activity): ?string
    {
        $employeeRef = $activity->EmployeeRef ?? null;

        if ($employeeRef === null) {
            return null;
        }

        if (is_object($employeeRef) && isset($employeeRef->value)) {
            return (string) $employeeRef->value;
        }

        return (string) $employeeRef;
    }
}
