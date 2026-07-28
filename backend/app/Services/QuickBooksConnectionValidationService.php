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
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QboEmployeeListService $employeeList,
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
        $this->assertSingleCompanyRealm($user, $token);

        try {
            $this->employeeList->assertCanListEmployees($token);
        } catch (HttpResponseException) {
            $this->quickBooks->disconnect($user);

            throw new QuickBooksOAuthException(
                'Insufficient QuickBooks permissions to manage employees.',
                null,
                'insufficient_permissions',
            );
        }
    }

    /**
     * Rejects OAuth when another administrator already connected a different company.
     *
     * @param  User  $user  Administrator who completed OAuth.
     * @param  QuickBooksToken  $token  Persisted OAuth token.
     * @return void
     */
    private function assertSingleCompanyRealm(User $user, QuickBooksToken $token): void
    {
        $conflictingRealm = QuickBooksToken::query()
            ->whereKeyNot($token->id)
            ->whereRelation('user', 'is_admin', true)
            ->where('realm_id', '!=', $token->realm_id)
            ->exists();

        if (! $conflictingRealm) {
            return;
        }

        $this->quickBooks->disconnect($user);

        throw new QuickBooksOAuthException(
            'Another QuickBooks company is already connected.',
            null,
            'realm_conflict',
        );
    }
}
