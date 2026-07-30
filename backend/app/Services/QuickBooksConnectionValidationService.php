<?php

/**
 * Validates new QuickBooks OAuth connections for administrator use.
 */

namespace App\Services;

use App\Exceptions\QuickBooksOAuthException;
use App\Models\QuickBooksToken;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Ensures OAuth tokens belong to the expected company and can read employees.
 */
class QuickBooksConnectionValidationService
{
    /**
     * Injects QuickBooks services for post-OAuth validation.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QboEmployeeListService  $employeeList  Employee list queries.
     * @param  OrganizationRealmService  $organizationRealm  Tenant realm binding rules.
     * @param  OrganizationTimezoneService  $organizationTimezone  Tenant company timezone sync.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QboEmployeeListService $employeeList,
        private readonly OrganizationRealmService $organizationRealm,
        private readonly OrganizationTimezoneService $organizationTimezone,
    ) {}

    /**
     * Validates realm consistency and employee read access for a new connection.
     *
     * @param  User  $user  Administrator who completed OAuth.
     * @param  QuickBooksToken  $token  Persisted OAuth token.
     * @return void
     */
    public function validateAdministratorConnection(User $user, QuickBooksToken $token): void
    {
        try {
            $this->organizationRealm->validateAndBindRealm($user, $token);
            $this->employeeList->assertCanListEmployees($token);

            $organization = $user->organization;

            if ($organization !== null) {
                $this->organizationTimezone->syncFromQuickBooks($organization, $token);
            }
        } catch (QuickBooksOAuthException $exception) {
            $this->quickBooks->disconnect($user);

            throw $exception;
        } catch (HttpResponseException) {
            $this->quickBooks->disconnect($user);

            throw new QuickBooksOAuthException(
                'Insufficient QuickBooks permissions to manage employees.',
                null,
                'insufficient_permissions',
            );
        }
    }
}
