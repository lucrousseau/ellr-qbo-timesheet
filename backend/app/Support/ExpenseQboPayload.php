<?php

/**
 * Maps local expenses to QuickBooks Purchase create payloads.
 */

namespace App\Support;

use App\Models\Expense;

/**
 * Builds QuickBooks Purchase write payloads from stored expense fields.
 */
final class ExpenseQboPayload
{
    /**
     * Maps a local expense to a QuickBooks Purchase create payload.
     *
     * @param  Expense  $expense  Local expense row.
     * @return array<string, mixed>
     */
    public static function fromExpense(Expense $expense): array
    {
        $payload = [
            'amount' => (float) $expense->amount,
            'txn_date' => $expense->txn_date?->toDateString(),
            'payment_type' => $expense->payment_type->value,
            'payment_account_ref' => $expense->payment_account_ref,
            'expense_account_ref' => $expense->expense_account_ref,
            'description' => $expense->description,
            'is_billable' => $expense->is_billable,
        ];

        if ($expense->vendor_ref) {
            $payload['vendor_ref'] = $expense->vendor_ref;
        }

        if ($expense->customer_ref) {
            $payload['customer_ref'] = $expense->customer_ref;
        }

        if ($expense->project_ref) {
            $payload['project_ref'] = $expense->project_ref;
        }

        return $payload;
    }
}
