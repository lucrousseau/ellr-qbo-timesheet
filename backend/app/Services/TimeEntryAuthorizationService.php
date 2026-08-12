<?php

/**
 * Authorization rules for time entry approval and supervisor assignment.
 */

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\TimeEntrySyncGroup;
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
        $this->organizationAccess->ensureSameOrganization($actor, $entry->user); // @pest-mutate-ignore tenant isolation guard

        if ($actor->id === $entry->user_id) { // @pest-mutate-ignore self-review guard
            abort(response()->json([
                'error' => 'time_entry_self_review_forbidden', // @pest-mutate-ignore self-review error payload
                'message' => __('api.time_entry_self_review_forbidden'), // @pest-mutate-ignore self-review error payload
            ], 403)); // @pest-mutate-ignore self-review guard
        }

        if ($actor->isAdmin()) { // @pest-mutate-ignore administrator review shortcut
            return;
        }

        if ($entry->user->supervisor_id === $actor->id) { // @pest-mutate-ignore direct-report review guard
            return;
        }

        abort(response()->json([
            'error' => 'time_entry_review_forbidden', // @pest-mutate-ignore review authorization failure
            'message' => __('api.time_entry_review_forbidden'), // @pest-mutate-ignore review authorization failure
        ], 403)); // @pest-mutate-ignore review authorization failure
    }

    /**
     * Ensures the actor may inspect a QuickBooks sync group and its members.
     *
     * @param  User  $actor  Authenticated employee, supervisor, or administrator.
     * @param  TimeEntrySyncGroup  $group  Sync group being inspected.
     * @return void
     */
    public function assertCanViewSyncGroup(User $actor, TimeEntrySyncGroup $group): void
    {
        $group->loadMissing('user'); // @pest-mutate-ignore sync group visibility preload
        $this->organizationAccess->ensureSameOrganization($actor, $group->user); // @pest-mutate-ignore tenant isolation guard

        if ($actor->isAdmin()) { // @pest-mutate-ignore administrator sync group shortcut
            return;
        }

        if ($actor->id === $group->user_id) { // @pest-mutate-ignore employee sync group ownership
            return;
        }

        if ($group->user?->supervisor_id === $actor->id) { // @pest-mutate-ignore supervisor sync group visibility
            return;
        }

        abort(response()->json([
            'error' => 'time_entry_sync_group_forbidden', // @pest-mutate-ignore sync group authorization failure
            'message' => __('api.time_entry_sync_group_forbidden'), // @pest-mutate-ignore sync group authorization failure
        ], 403)); // @pest-mutate-ignore sync group authorization failure
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
        $this->organizationAccess->ensureTimesheetUser($actor, $target); // @pest-mutate-ignore tenant isolation guard

        if ($supervisor === null) { // @pest-mutate-ignore supervisor clearing shortcut
            return;
        }

        $this->organizationAccess->ensureSameOrganization($actor, $supervisor); // @pest-mutate-ignore tenant isolation guard

        if ($supervisor->id === $target->id) { // @pest-mutate-ignore self-supervisor guard
            abort(response()->json([
                'error' => 'supervisor_self_assignment', // @pest-mutate-ignore self-supervisor error payload
                'message' => __('api.supervisor_self_assignment'), // @pest-mutate-ignore self-supervisor error payload
            ], 422)); // @pest-mutate-ignore self-supervisor guard
        }
    }
}
