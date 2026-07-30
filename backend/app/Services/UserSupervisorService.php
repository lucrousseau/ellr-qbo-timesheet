<?php

/**
 * Assigns supervisors to timesheet users for approval routing.
 */

namespace App\Services;

use App\Models\User;

/**
 * Updates supervisor assignments for provisioned timesheet users.
 */
class UserSupervisorService
{
    /**
     * Injects authorization and organization access checks.
     *
     * @param  TimeEntryAuthorizationService  $authorization  Supervisor assignment guard.
     */
    public function __construct(
        private readonly TimeEntryAuthorizationService $authorization,
    ) {}

    /**
     * Assigns or clears the supervisor for a timesheet user.
     *
     * @param  User  $actor  Authenticated administrator.
     * @param  User  $target  Timesheet user receiving the supervisor.
     * @param  int|null  $supervisorId  Supervisor user id or null to clear.
     * @return User
     */
    public function updateSupervisor(User $actor, User $target, ?int $supervisorId): User
    {
        $supervisor = $supervisorId !== null
            ? User::query()->whereKey($supervisorId)->firstOrFail()
            : null;

        $this->authorization->assertCanAssignSupervisor($actor, $target, $supervisor);

        $target->supervisor_id = $supervisor?->id;
        $target->save();

        return $target->refresh()->load(['organization', 'userLevel', 'supervisor']);
    }
}
