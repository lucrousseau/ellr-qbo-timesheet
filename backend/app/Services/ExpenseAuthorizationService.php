<?php

/**
 * Authorization rules for expense approval reviews.
 */

namespace App\Services;

use App\Models\Expense;
use App\Models\User;

/**
 * Guards who may review pending expenses before QuickBooks sync.
 */
class ExpenseAuthorizationService
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
     * Ensures the actor may review a pending expense.
     *
     * @param  User  $actor  Authenticated reviewer.
     * @param  Expense  $expense  Expense being reviewed.
     * @return void
     */
    public function assertCanReview(User $actor, Expense $expense): void
    {
        $this->organizationAccess->ensureSameOrganization($actor, $expense->user);

        if ($actor->id === $expense->user_id) {
            abort(response()->json([
                'error' => 'expense_self_review_forbidden',
                'message' => __('api.expense_self_review_forbidden'),
            ], 403));
        }

        if ($actor->isAdmin()) {
            return;
        }

        if ($expense->user->supervisor_id === $actor->id) {
            return;
        }

        abort(response()->json([
            'error' => 'expense_review_forbidden',
            'message' => __('api.expense_review_forbidden'),
        ], 403));
    }
}
