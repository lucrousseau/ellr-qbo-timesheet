<?php

/**
 * Administrator-assigned QuickBooks customer access for timesheet users.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Support\QboRefNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Syncs and reads per-user QuickBooks customer assignments managed in the admin app.
 */
class UserQboCustomerAssignmentService
{
    /**
     * Injects customer list and timer session services.
     *
     * @param  QboCustomerListService  $customers  Customer list and resolution service.
     * @param  TimeTrackerService  $timeTracker  Active timer session service.
     */
    public function __construct(
        private readonly QboCustomerListService $customers,
        private readonly TimeTrackerService $timeTracker,
    ) {}

    /**
     * Returns customer access settings for the given timesheet user.
     *
     * @param  User  $user  Timesheet user account.
     * @return array{all_customers_access: bool, data: array<int, array{id: string, display_name: string}>}
     */
    public function describeAccess(User $user): array
    {
        return [
            'all_customers_access' => $user->qbo_all_customers_access,
            'data' => $user->qbo_all_customers_access ? [] : $user->assignedQboCustomerPickerRows(),
        ];
    }

    /**
     * Replaces customer access for a provisioned timesheet user.
     *
     * @param  User  $user  Target timesheet user.
     * @param  QuickBooksToken  $token  Connected QuickBooks token.
     * @param  bool  $allCustomersAccess  When true, grants access to every active QuickBooks customer.
     * @param  list<string>  $customerRefs  QuickBooks customer identifiers when access is restricted.
     * @return array{all_customers_access: bool, data: array<int, array{id: string, display_name: string}>}
     */
    public function sync(
        User $user,
        QuickBooksToken $token,
        bool $allCustomersAccess,
        array $customerRefs,
    ): array {
        $this->ensureTimesheetUser($user);

        if ($allCustomersAccess) {
            $user->forceFill(['qbo_all_customers_access' => true])->save();
            $user->qboCustomers()->delete();
        } else {
            $normalizedRefs = $this->normalizeRefs($customerRefs);
            $resolvedById = $this->resolveAssignableCustomers($token, $normalizedRefs);

            DB::transaction(function () use ($user, $normalizedRefs, $resolvedById): void {
                $user->forceFill(['qbo_all_customers_access' => false])->save();
                $user->qboCustomers()->delete();

                foreach ($normalizedRefs as $ref) {
                    $user->qboCustomers()->create([
                        'qbo_customer_ref' => $ref,
                        'qbo_customer_name' => $resolvedById[$ref]['display_name'],
                    ]);
                }
            });
        }

        $this->timeTracker->sanitizeActiveSessionIfExists($user, $token);

        return $this->describeAccess($user->fresh(['qboCustomers']));
    }

    /**
     * Ensures the target user is a provisioned timesheet account.
     *
     * @param  User  $user  Target user account.
     * @return void
     */
    public function ensureTimesheetUser(User $user): void
    {
        if ($user->isAdmin() || $user->qbo_employee_ref === null || $user->qbo_employee_ref === '') {
            abort(404);
        }
    }

    /**
     * Maps assignable customers from the admin picker list by identifier.
     *
     * @param  QuickBooksToken  $token  Connected QuickBooks token.
     * @param  list<string>  $normalizedRefs  Normalized QuickBooks customer identifiers.
     * @return array<string, array{id: string, display_name: string}>
     */
    private function resolveAssignableCustomers(QuickBooksToken $token, array $normalizedRefs): array
    {
        if ($normalizedRefs === []) {
            return [];
        }

        $availableById = [];

        foreach ($this->customers->listAllActive($token, true) as $customer) {
            $id = QboRefNormalizer::normalize((string) $customer['id']);

            if ($id === null) {
                continue;
            }

            $availableById[$id] = [
                'id' => $id,
                'display_name' => $customer['display_name'],
            ];
        }

        $resolvedById = [];

        foreach ($normalizedRefs as $ref) {
            if (! isset($availableById[$ref])) {
                abort(response()->json(['message' => __('api.admin_invalid_customer_assignment')], 422));
            }

            $resolvedById[$ref] = $availableById[$ref];
        }

        return $resolvedById;
    }

    /**
     * Normalizes customer references to unique numeric QuickBooks identifiers.
     *
     * @param  list<string>  $customerRefs  Raw customer identifiers.
     * @return list<string>
     */
    private function normalizeRefs(array $customerRefs): array
    {
        $refs = [];

        foreach ($customerRefs as $ref) {
            $normalized = QboRefNormalizer::normalize((string) $ref);

            if ($normalized !== null) {
                $refs[$normalized] = $normalized;
            }
        }

        return array_values($refs);
    }
}
