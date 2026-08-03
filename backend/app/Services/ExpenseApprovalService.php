<?php

/**
 * Supervisor approval workflow and QuickBooks Purchase sync for local expenses.
 */

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Jobs\SyncApprovedExpenseToQuickBooksJob;
use App\Models\Expense;
use App\Models\User;
use App\Support\ExpenseQboPayload;
use Illuminate\Support\Facades\DB;

/**
 * Lists pending expenses for reviewers and queues approved rows for QuickBooks sync.
 */
class ExpenseApprovalService
{
    /**
     * Injects authorization, picker validation, and presentation helpers.
     *
     * @param  ExpenseAuthorizationService  $authorization  Review permission checks.
     * @param  ExpensePickerValidationService  $pickerValidation  Expense picker validator.
     * @param  QuickBooksTokenResolverService  $tokenResolver  Resolves organization QBO token.
     * @param  ExpensePresentationService  $presentation  Read-time label resolution for API rows.
     */
    public function __construct(
        private readonly ExpenseAuthorizationService $authorization,
        private readonly ExpensePickerValidationService $pickerValidation,
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly ExpensePresentationService $presentation,
    ) {}

    /**
     * Lists pending expenses the actor may review.
     *
     * @param  User  $actor  Supervisor or administrator.
     * @param  int  $startPosition  One-based pagination offset.
     * @param  int  $maxResults  Maximum rows per page.
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|bool>}
     */
    public function listPendingForReviewer(User $actor, int $startPosition, int $maxResults): array
    {
        $query = Expense::query()
            ->with(['user', 'reviewedBy'])
            ->where('organization_id', $actor->organization_id)
            ->where('status', ExpenseStatus::Pending)
            ->when(! $actor->isAdmin(), function ($builder) use ($actor): void {
                $builder->whereIn('user_id', User::query()
                    ->select('id')
                    ->where('supervisor_id', $actor->id));
            })
            ->orderBy('txn_date')
            ->orderBy('id');

        $total = (clone $query)->count();
        $offset = max(0, $startPosition - 1);
        $expenses = $query->offset($offset)->limit($maxResults)->get();
        $count = $expenses->count();

        return [
            'data' => $this->presentation->collection($expenses, $actor),
            'meta' => [
                'count' => $count,
                'max_results' => $maxResults,
                'start_position' => $startPosition,
                'truncated' => $offset + $count < $total,
            ],
        ];
    }

    /**
     * Approves a pending expense and queues QuickBooks Purchase synchronization.
     *
     * @param  User  $actor  Supervisor or administrator.
     * @param  int  $id  Local expense identifier.
     * @return Expense
     */
    public function approve(User $actor, int $id): Expense
    {
        [$expense, $employee, $token] = DB::transaction(function () use ($actor, $id): array {
            $expense = $this->findPendingExpense($id, lock: true);
            $this->authorization->assertCanReview($actor, $expense);

            $employee = $expense->user;
            $token = $this->tokenResolver->resolve($actor);
            $this->pickerValidation->assertValidExpense($employee, $token, $expense);

            $expense->forceFill([
                'status' => ExpenseStatus::Approved,
                'reviewed_by_id' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            return [
                $expense->refresh()->load(['user', 'reviewedBy']),
                $employee,
                $token,
            ];
        });

        SyncApprovedExpenseToQuickBooksJob::dispatch(
            $expense->id,
            $employee->id,
            $token->id,
            ExpenseQboPayload::fromExpense($expense),
        );

        return $expense->refresh()->load(['user', 'reviewedBy']);
    }

    /**
     * Rejects a pending expense without synchronizing it to QuickBooks.
     *
     * @param  User  $actor  Supervisor or administrator.
     * @param  int  $id  Local expense identifier.
     * @param  string|null  $reason  Optional rejection reason for the employee.
     * @return Expense
     */
    public function reject(User $actor, int $id, ?string $reason): Expense
    {
        return DB::transaction(function () use ($actor, $id, $reason): Expense {
            $expense = $this->findPendingExpense($id, lock: true);
            $this->authorization->assertCanReview($actor, $expense);

            $expense->forceFill([
                'status' => ExpenseStatus::Rejected,
                'reviewed_by_id' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            return $expense->refresh()->load(['user', 'reviewedBy']);
        });
    }

    /**
     * Loads a pending expense by identifier.
     *
     * @param  int  $id  Local expense identifier.
     * @param  bool  $lock  When true, locks the row for update.
     * @return Expense
     */
    private function findPendingExpense(int $id, bool $lock = false): Expense
    {
        $query = Expense::query()
            ->with('user')
            ->whereKey($id)
            ->where('status', ExpenseStatus::Pending);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOr(function (): never {
            abort(response()->json(['message' => __('api.expense_not_found')], 404));
        });
    }
}
