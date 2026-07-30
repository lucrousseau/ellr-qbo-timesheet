<?php

/**
 * Platform-level organization provisioning and deletion for super administrators.
 */

namespace App\Services;

use App\Exceptions\OrganizationLifecycleException;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates, updates, and deletes tenant organizations across the shared database.
 */
class OrganizationLifecycleService
{
    /**
     * @param  OrganizationRegistrationService  $registration  Shared tenant signup logic.
     * @param  AuthSessionService  $authSessions  Session and token invalidation.
     * @param  QuickBooksService  $quickBooks  QuickBooks OAuth disconnect helper.
     */
    public function __construct(
        private readonly OrganizationRegistrationService $registration,
        private readonly AuthSessionService $authSessions,
        private readonly QuickBooksService $quickBooks,
    ) {}

    /**
     * Lists all tenant organizations with aggregate user counts.
     *
     * @return Collection<int, Organization>
     */
    public function listOrganizations(): Collection
    {
        return Organization::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();
    }

    /**
     * Creates a tenant organization and its founding administrator account.
     *
     * @param  array{name: string, email: string, password: string, organization_name: string}  $validated  Provisioning payload.
     * @return Organization
     */
    public function createOrganization(array $validated): Organization
    {
        $administrator = $this->registration->registerAdministrator([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'organization_name' => $validated['organization_name'],
        ]);

        $administrator->forceFill(['email_verified_at' => now()])->save();

        return $administrator->organization()->firstOrFail();
    }

    /**
     * Updates mutable organization attributes.
     *
     * @param  Organization  $organization  Tenant organization to update.
     * @param  array{name: string}  $validated  Validated update payload.
     * @return Organization
     */
    public function updateOrganization(Organization $organization, array $validated): Organization
    {
        $organization->update([
            'name' => $validated['name'],
        ]);

        return $organization->fresh();
    }

    /**
     * Deletes a tenant organization, its users, and any linked QuickBooks realm data.
     *
     * @param  User  $actor  Super administrator performing the deletion.
     * @param  Organization  $organization  Tenant organization to delete.
     * @return void
     */
    public function deleteOrganization(User $actor, Organization $organization): void
    {
        $this->guardDeletable($actor, $organization);

        DB::transaction(function () use ($organization): void {
            $organization->load('users');

            foreach ($organization->users as $user) {
                $this->authSessions->invalidateUserSessions($user);

                if ($user->quickBooksToken !== null) {
                    try {
                        $this->quickBooks->disconnect($user);
                    } catch (\Throwable) {
                        $user->quickBooksToken?->delete();
                    }
                }
            }

            $organization->delete();
        });
    }

    /**
     * Prevents unsafe organization deletions that would lock out platform operators.
     *
     * @param  User  $actor  Super administrator performing the deletion.
     * @param  Organization  $organization  Tenant organization to delete.
     * @return void
     */
    private function guardDeletable(User $actor, Organization $organization): void
    {
        if ($actor->organization_id === $organization->id) {
            throw new OrganizationLifecycleException(
                'You cannot delete the organization that owns your account.',
                'cannot_delete_own_organization',
            );
        }

        $superAdminsInOrganization = User::query()
            ->where('organization_id', $organization->id)
            ->where('is_super_admin', true)
            ->count();

        if ($superAdminsInOrganization === 0) {
            return;
        }

        $remainingSuperAdmins = User::query()
            ->where('is_super_admin', true)
            ->where('organization_id', '!=', $organization->id)
            ->count();

        if ($remainingSuperAdmins === 0) {
            throw new OrganizationLifecycleException(
                'Deleting this organization would remove the last platform super administrator.',
                'cannot_delete_last_super_admin_organization',
            );
        }
    }
}
