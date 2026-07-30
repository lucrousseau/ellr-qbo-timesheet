<?php

/**
 * Authorization rules for time entry approval and supervisor assignment.
 */

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\User;

/**
 * Guards who may review or assign supervisors for time entries.
 */
class TimeEntryAuthorizationService
{
    /**
     * Injects organization access checks for tenant isolation.
     *
     * @param  OrganizationAccessService  $organizationAccess  Tenant isolation guard.
     */
    public function __construct(
        private readonly OrganizationAccessService $organizationAccess,
    ) {}

    /**
     * Ensures the actor may review a pending time entry.
     *
     * @param  User  $actor  Authenticated reviewer.
     * @param  TimeEntry  $entry  Entry being reviewed.
     * @return void
     */
    public function assertCanReview(User $actor, TimeEntry $entry): void
    {
        $this->organizationAccess->ensureSameOrganization($actor, $entry->user);

        if ($actor->id === $entry->user_id) {
            abort(response()->json([
                'error' => 'time_entry_self_review_forbidden',
                'message' => __('api.time_entry_self_review_forbidden'),
            ], 403));
        }

        if ($actor->isAdmin()) {
            return;
        }

        if ($entry->user->supervisor_id === $actor->id) {
            return;
        }

        abort(response()->json([
            'error' => 'time_entry_review_forbidden',
            'message' => __('api.time_entry_review_forbidden'),
        ], 403));
    }

    /**
     * Ensures the actor may assign a supervisor to a timesheet user.
     *
     * @param  User  $actor  Authenticated administrator.
     * @param  User  $target  Timesheet user receiving the supervisor.
     * @param  User|null  $supervisor  Proposed supervisor or null to clear.
     * @return void
     */
    public function assertCanAssignSupervisor(User $actor, User $target, ?User $supervisor): void
    {
        $this->organizationAccess->ensureTimesheetUser($actor, $target);

        if ($supervisor === null) {
            return;
        }

        $this->organizationAccess->ensureSameOrganization($actor, $supervisor);

        if ($supervisor->id === $target->id) {
            abort(response()->json([
                'error' => 'supervisor_self_assignment',
                'message' => __('api.supervisor_self_assignment'),
            ], 422));
        }
    }
}
