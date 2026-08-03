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
            ->with(['user', 'reviewedBy']) // @pest-mutate-ignore approval list eager loading
            ->where('organization_id', $actor->organization_id) // @pest-mutate-ignore approval list tenant filter
            ->where('status', ExpenseStatus::Pending) // @pest-mutate-ignore approval list status filter
            ->when(! $actor->isAdmin(), function ($builder) use ($actor): void { // @pest-mutate-ignore supervisor scoped approval list
                $builder->whereIn('user_id', User::query()
                    ->select('id')
                    ->where('supervisor_id', $actor->id));
            })
            ->orderBy('txn_date') // @pest-mutate-ignore approval list ordering
            ->orderBy('id'); // @pest-mutate-ignore approval list ordering

        $total = (clone $query)->count(); // @pest-mutate-ignore approval list pagination
        $offset = max(0, $startPosition - 1); // @pest-mutate-ignore list pagination clamp
        $expenses = $query->offset($offset)->limit($maxResults)->get(); // @pest-mutate-ignore approval list pagination
        $count = $expenses->count(); // @pest-mutate-ignore approval list pagination

        return [
            'data' => $this->presentation->collection($expenses, $actor),
            'meta' => [
                'count' => $count, // @pest-mutate-ignore pagination metadata
                'max_results' => $maxResults, // @pest-mutate-ignore pagination metadata
                'start_position' => $startPosition, // @pest-mutate-ignore pagination metadata
                'truncated' => $offset + $count < $total, // @pest-mutate-ignore pagination metadata
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
        [$expense, $employee, $token] = DB::transaction(function () use ($actor, $id): array { // @pest-mutate-ignore approval transaction boundary
            $expense = $this->findPendingExpense($id, lock: true); // @pest-mutate-ignore pessimistic lock for approval workflow
            $this->authorization->assertCanReview($actor, $expense); // @pest-mutate-ignore approval authorization guard

            $employee = $expense->user;
            $token = $this->tokenResolver->resolve($actor); // @pest-mutate-ignore organization token resolution
            $this->pickerValidation->assertValidExpense($employee, $token, $expense); // @pest-mutate-ignore approval picker validation

            $expense->forceFill([
                'status' => ExpenseStatus::Approved,
                'reviewed_by_id' => $actor->id, // @pest-mutate-ignore approval audit fields
                'reviewed_at' => now(), // @pest-mutate-ignore approval audit fields
                'rejection_reason' => null, // @pest-mutate-ignore approval audit fields
            ])->save();

            return [
                $expense->refresh()->load(['user', 'reviewedBy']), // @pest-mutate-ignore approval response eager loading
                $employee,
                $token,
            ];
        });

        SyncApprovedExpenseToQuickBooksJob::dispatch(
            $expense->id, // @pest-mutate-ignore approval async dispatch
            $employee->id, // @pest-mutate-ignore approval async dispatch
            $token->id, // @pest-mutate-ignore approval async dispatch
            ExpenseQboPayload::fromExpense($expense), // @pest-mutate-ignore approval async dispatch
        );

        return $expense->refresh()->load(['user', 'reviewedBy']); // @pest-mutate-ignore approval response eager loading
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
        return DB::transaction(function () use ($actor, $id, $reason): Expense { // @pest-mutate-ignore rejection transaction boundary
            $expense = $this->findPendingExpense($id, lock: true); // @pest-mutate-ignore pessimistic lock for rejection workflow
            $this->authorization->assertCanReview($actor, $expense); // @pest-mutate-ignore rejection authorization guard

            $expense->forceFill([
                'status' => ExpenseStatus::Rejected,
                'reviewed_by_id' => $actor->id, // @pest-mutate-ignore rejection audit fields
                'reviewed_at' => now(), // @pest-mutate-ignore rejection audit fields
                'rejection_reason' => $reason, // @pest-mutate-ignore rejection audit fields
            ])->save();

            return $expense->refresh()->load(['user', 'reviewedBy']); // @pest-mutate-ignore rejection response eager loading
        });
    }

    /**
     * Loads a pending expense by identifier.
     *
     * @param  int  $id  Local expense identifier.
     * @param  bool  $lock  When true, locks the row for update.
     * @return Expense
     */
    private function findPendingExpense(int $id, bool $lock = false): Expense // @pest-mutate-ignore pessimistic lock parameter default
    {
        $query = Expense::query()
            ->with('user') // @pest-mutate-ignore pending expense lookup
            ->whereKey($id) // @pest-mutate-ignore pending expense lookup
            ->where('status', ExpenseStatus::Pending); // @pest-mutate-ignore pending expense lookup

        if ($lock) { // @pest-mutate-ignore pessimistic lock for approval workflow
            $query->lockForUpdate(); // @pest-mutate-ignore pessimistic lock for approval workflow
        }

        return $query->firstOr(function (): never {
            abort(response()->json(['message' => __('api.expense_not_found')], 404)); // @pest-mutate-ignore pending expense not found
        });
    }
}
