<?php

/**
 * Queued QuickBooks Purchase sync for an approved local expense.
 */

namespace App\Jobs;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates the approved expense as a QuickBooks Purchase and stores the returned identifier.
 */
class SyncApprovedExpenseToQuickBooksJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $expenseId  Local expense primary key.
     * @param  int  $employeeId  Employee who logged the expense.
     * @param  int  $tokenId  Organization QuickBooks token primary key.
     * @param  array<string, mixed>  $payload  QuickBooks Purchase create payload.
     */
    public function __construct(
        public readonly int $expenseId,
        public readonly int $employeeId,
        public readonly int $tokenId,
        public readonly array $payload,
    ) {}

    /**
     * Pushes the approved expense to QuickBooks while keeping the approval decision intact.
     *
     * @param  PurchaseService  $purchases  QuickBooks Purchase writer.
     * @return void
     */
    public function handle(PurchaseService $purchases): void
    {
        $expense = Expense::query()->find($this->expenseId);
        $employee = User::query()->find($this->employeeId);
        $token = QuickBooksToken::query()->find($this->tokenId);

        if (
            $expense === null
            || $employee === null
            || $token === null
            || $expense->status !== ExpenseStatus::Approved
            || $expense->qbo_id !== null
        ) {
            return;
        }

        try {
            $qboPurchase = $purchases->createForUser($employee, $token, $this->payload);
        } catch (Throwable $exception) {
            Log::warning('QuickBooks synchronization failed for approved expense', [
                'expense_id' => $expense->id,
                'employee_id' => $employee->id,
                'exception' => $exception->getMessage(),
            ]);

            throw new \RuntimeException('QuickBooks synchronization failed for expense '.$expense->id, 0, $exception);
        }

        Expense::query()
            ->whereKey($expense->id)
            ->where('status', ExpenseStatus::Approved)
            ->whereNull('qbo_id')
            ->update(['qbo_id' => (string) $qboPurchase->Id]);
    }

    /**
     * Logs a terminal queue failure without reverting the approval decision.
     *
     * @param  Throwable  $exception  Queue failure reason.
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Approved expense QuickBooks sync job failed permanently', [
            'expense_id' => $this->expenseId,
            'employee_id' => $this->employeeId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
