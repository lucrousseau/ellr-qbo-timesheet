<?php

/**
 * Creates timesheet user accounts linked to QuickBooks employees.
 */

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provisions verified timesheet users and sends password-set invitations.
 */
class UserProvisioningService
{
    /**
     * Injects employee mapping and invitation services.
     *
     * @param  QboEmployeeService  $qboEmployee  Employee mapping validation.
     * @param  QuickBooksTokenResolverService  $tokenResolver  QuickBooks token resolver.
     * @param  TimesheetInvitationService  $invitation  Password-set email sender.
     */
    public function __construct(
        private readonly QboEmployeeService $qboEmployee,
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly TimesheetInvitationService $invitation,
    ) {}

    /**
     * Creates a timesheet user mapped to a QuickBooks employee and sends an invite email.
     *
     * @param  User  $admin  Administrator performing the provisioning.
     * @param  array<string, mixed>  $validated  Validated employee payload.
     * @return User
     */
    public function provisionTimesheetUser(User $admin, array $validated): User
    {
        return DB::transaction(function () use ($admin, $validated): User {
            $token = $this->tokenResolver->resolve($admin);
            $employeeRef = (string) $validated['qbo_employee_ref'];
            $identity = $this->qboEmployee->resolveEmployeeIdentity($token, $employeeRef);

            $user = User::query()->create([
                'name' => $identity['display_name'],
                'email' => $identity['email'],
                'password' => Str::password(32),
                'locale' => $admin->preferredLocale(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $mapped = $this->qboEmployee->updateMapping($admin, $user, $validated);
            $this->invitation->send($mapped);

            return $mapped;
        });
    }

    /**
     * Lists non-administrator users with a QuickBooks employee mapping.
     *
     * @return Collection<int, User>
     */
    public function listTimesheetUsers()
    {
        return User::query()
            ->where('is_admin', false)
            ->whereNotNull('qbo_employee_ref')
            ->orderBy('name')
            ->get();
    }
}
