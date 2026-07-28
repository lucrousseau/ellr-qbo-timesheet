<?php

/**
 * Validates and persists QuickBooks employee mappings for application users.
 */

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Models\User;

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
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QuickBooksTokenResolverService $tokenResolver,
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
        $token = $this->tokenResolver->resolve($actor);
        $employeeRef = (string) $validated['qbo_employee_ref'];

        if (! $this->employeeExists($token, $employeeRef)) {
            abort(response()->json([
                'message' => __('api.qbo_employee_not_found'),
                'error' => ApiErrorCode::QboEmployeeInvalid->value,
            ], 422));
        }

        $target->update([
            'qbo_employee_ref' => $employeeRef,
            'qbo_employee_name' => $validated['qbo_employee_name'] ?? null,
        ]);

        return $target->fresh();
    }

    /**
     * Checks whether an employee record exists in QuickBooks.
     *
     * @param  QuickBooksToken  $token  OAuth token for the connected company.
     * @param  string  $employeeRef  QuickBooks employee identifier.
     * @return bool
     */
    private function employeeExists(QuickBooksToken $token, string $employeeRef): bool
    {
        $dataService = $this->quickBooks->dataService($token);
        $employee = $dataService->FindById('Employee', $employeeRef);

        if ($error = $dataService->getLastError()) {
            throw new QuickBooksException(
                'QuickBooks employee lookup failed.',
                $error->getResponseBody(),
            );
        }

        return $employee !== null;
    }
}
