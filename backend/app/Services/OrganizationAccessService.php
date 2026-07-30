<?php

/**
 * Enforces tenant isolation between users in the same organization.
 */

namespace App\Services;

use App\Models\User;

/**
 * Guards cross-organization access for administrator and timesheet flows.
 */
class OrganizationAccessService
{
    /**
     * Aborts with 404 when two users do not belong to the same organization.
     *
     * @param  User  $actor  Authenticated user performing the action.
     * @param  User  $target  User being accessed or modified.
     * @return void
     */
    public function ensureSameOrganization(User $actor, User $target): void
    {
        if ($actor->organization_id !== $target->organization_id) {
            abort(404);
        }
    }

    /**
     * Ensures the target is a provisioned timesheet user in the actor's organization.
     *
     * @param  User  $actor  Authenticated administrator.
     * @param  User  $target  Target timesheet user.
     * @return void
     */
    public function ensureTimesheetUser(User $actor, User $target): void
    {
        $this->ensureSameOrganization($actor, $target);

        if ($target->isAdmin() || $target->qbo_employee_ref === null || $target->qbo_employee_ref === '') {
            abort(404);
        }
    }
}
