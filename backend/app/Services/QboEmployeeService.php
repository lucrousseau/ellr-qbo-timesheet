<?php

/**
 * Validates and persists QuickBooks employee mappings for application users.
 */

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Support\PersonName;
use App\Support\QboEmployeeEmail;
use Illuminate\Validation\ValidationException;

/**
 * Confirms an employee exists in QBO before saving a user mapping.
 */
class QboEmployeeService
{
    /**
     * Injects QuickBooks services for employee lookup.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QuickBooksTokenResolverService  $tokenResolver  Token resolver for the acting admin.
     * @param  OrganizationAccessService  $organizationAccess  Tenant isolation checks.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly OrganizationAccessService $organizationAccess,
    ) {}

    /**
     * Updates the QBO employee mapping on a user after validating against QBO.
     *
     * @param  User  $actor  Administrator performing the update (must have QBO connected).
     * @param  User  $target  User receiving the employee mapping.
     * @param  array<string, mixed>  $validated  Validated employee payload.
     * @return User
     */
    public function updateMapping(User $actor, User $target, array $validated): User
    {
        $this->organizationAccess->ensureSameOrganization($actor, $target);

        $token = $this->tokenResolver->resolve($actor);
        $employeeRef = (string) $validated['qbo_employee_ref'];
        $identity = $this->resolveEmployeeIdentity($token, $employeeRef, $target);

        $attributes = [
            'qbo_employee_ref' => $employeeRef,
        ];

        if (! $target->isAdmin()) {
            $attributes['first_name'] = $identity['first_name'];
            $attributes['last_name'] = $identity['last_name'];
            $attributes['email'] = $identity['email'];
        }

        $target->update($attributes);

        return $target->fresh();
    }

    /**
     * Resolves a QuickBooks employee identity for provisioning or login sync.
     *
     * @param  QuickBooksToken  $token  OAuth token for the connected company.
     * @param  string  $employeeRef  QuickBooks employee identifier.
     * @param  User|null  $exceptUser  User to ignore when checking email uniqueness.
     * @return array{first_name: string, last_name: string, display_name: string, email: string}
     */
    public function resolveEmployeeIdentity(QuickBooksToken $token, string $employeeRef, ?User $exceptUser = null): array
    {
        $employee = $this->findEmployee($token, $employeeRef);

        if ($employee === null) {
            $this->abortEmployeeError(ApiErrorCode::QboEmployeeInvalid, 'api.qbo_employee_not_found');
        }

        if ($employee['email'] === null) {
            $this->abortEmployeeError(ApiErrorCode::QboEmployeeEmailMissing, 'api.qbo_employee_email_missing');
        }

        if (
            User::query()
                ->when($exceptUser, fn ($query) => $query->whereKeyNot($exceptUser->id))
                ->where('email', $employee['email'])
                ->exists()
        ) {
            // Login emails are globally unique; two tenants cannot share the same account email.
            $this->abortEmployeeError(ApiErrorCode::QboEmployeeEmailConflict, 'api.qbo_employee_email_conflict');
        }

        return [
            'first_name' => $employee['first_name'],
            'last_name' => $employee['last_name'],
            'display_name' => $employee['display_name'],
            'email' => $employee['email'],
        ];
    }

    /**
     * Synchronizes a timesheet user's profile fields from QuickBooks.
     *
     * @param  User  $user  Timesheet user linked to a QBO employee.
     * @param  QuickBooksToken  $token  OAuth token for the connected company.
     * @return User
     */
    public function syncTimesheetUserFromQuickBooks(User $user, QuickBooksToken $token): User
    {
        $identity = $this->resolveEmployeeIdentity($token, (string) $user->qbo_employee_ref, $user);

        return $this->syncTimesheetUserFromIdentity($user, $identity);
    }

    /**
     * Applies a resolved QuickBooks employee identity to a timesheet user.
     *
     * @param  User  $user  Timesheet user linked to a QBO employee.
     * @param  array{first_name: string, last_name: string, display_name: string, email: string}  $identity  Resolved employee identity.
     * @return User
     */
    public function syncTimesheetUserFromIdentity(User $user, array $identity): User
    {
        if ($this->timesheetIdentityMatches($user, $identity)) {
            return $user;
        }

        if ($this->emailTakenByAnotherUser($user, $identity['email'])) {
            throw ValidationException::withMessages([
                'email' => [__('api.qbo_employee_email_conflict')],
            ]);
        }

        $user->update([
            'first_name' => $identity['first_name'],
            'last_name' => $identity['last_name'],
            'email' => $identity['email'],
        ]);

        return $user->fresh();
    }

    /**
     * Checks whether an employee record exists in QuickBooks.
     *
     * @param  QuickBooksToken  $token  OAuth token for the connected company.
     * @param  string  $employeeRef  QuickBooks employee identifier.
     * @return bool
     */
    public function employeeExists(QuickBooksToken $token, string $employeeRef): bool
    {
        return $this->findEmployee($token, $employeeRef) !== null;
    }

    /**
     * Loads a QuickBooks employee record when it exists.
     *
     * @param  QuickBooksToken  $token  OAuth token for the connected company.
     * @param  string  $employeeRef  QuickBooks employee identifier.
     * @return array{first_name: string, last_name: string, display_name: string, email: string|null}|null
     */
    public function findEmployee(QuickBooksToken $token, string $employeeRef): ?array
    {
        $dataService = $this->quickBooks->dataService($token);
        $employee = $dataService->FindById('Employee', $employeeRef);

        if ($error = $dataService->getLastError()) {
            throw new QuickBooksException(
                'QuickBooks employee lookup failed.',
                $error->getResponseBody(),
            );
        }

        if ($employee === null) {
            return null;
        }

        $names = PersonName::fromQuickBooksEmployee($employee);

        return [
            'first_name' => $names['first_name'],
            'last_name' => $names['last_name'],
            'display_name' => $names['display_name'],
            'email' => QboEmployeeEmail::fromEmployee($employee),
        ];
    }

    /**
     * Returns whether a timesheet user already matches the resolved QuickBooks identity.
     *
     * @param  User  $user  Timesheet user linked to a QBO employee.
     * @param  array{first_name: string, last_name: string, display_name: string, email: string}  $identity  Resolved employee identity.
     * @return bool
     */
    private function timesheetIdentityMatches(User $user, array $identity): bool
    {
        return $user->first_name === $identity['first_name']
            && $user->last_name === $identity['last_name']
            && $user->email === $identity['email'];
    }

    /**
     * Returns whether another account already uses the given email address.
     *
     * @param  User  $user  Timesheet user being synchronized.
     * @param  string  $email  Candidate email address.
     * @return bool
     */
    private function emailTakenByAnotherUser(User $user, string $email): bool
    {
        return User::query()
            ->where('email', $email)
            ->whereKeyNot($user->id)
            ->exists();
    }

    /**
     * Aborts with a standardized QuickBooks employee validation response.
     *
     * @param  ApiErrorCode  $code  API error code.
     * @param  string  $messageKey  Translation key under lang/{locale}/api.php.
     * @return never
     */
    private function abortEmployeeError(ApiErrorCode $code, string $messageKey): never
    {
        abort(response()->json([
            'message' => __($messageKey),
            'error' => $code->value,
        ], 422));
    }
}
