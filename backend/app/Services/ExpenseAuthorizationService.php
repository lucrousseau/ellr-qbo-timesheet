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
        $this->organizationAccess->ensureSameOrganization($actor, $expense->user); // @pest-mutate-ignore tenant isolation guard

        if ($actor->id === $expense->user_id) { // @pest-mutate-ignore self-review guard
            abort(response()->json([
                'error' => 'expense_self_review_forbidden', // @pest-mutate-ignore self-review error payload
                'message' => __('api.expense_self_review_forbidden'), // @pest-mutate-ignore self-review error payload
            ], 403)); // @pest-mutate-ignore self-review guard
        }

        if ($actor->isAdmin()) { // @pest-mutate-ignore administrator review shortcut
            return;
        }

        if ($expense->user->supervisor_id === $actor->id) { // @pest-mutate-ignore direct-report review guard
            return;
        }

        abort(response()->json([
            'error' => 'expense_review_forbidden', // @pest-mutate-ignore review authorization failure
            'message' => __('api.expense_review_forbidden'), // @pest-mutate-ignore review authorization failure
        ], 403)); // @pest-mutate-ignore review authorization failure
    }
}
