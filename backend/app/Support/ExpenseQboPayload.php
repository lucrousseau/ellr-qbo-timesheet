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
            'amount' => (float) $expense->amount, // @pest-mutate-ignore QBO payload field mapping
            'txn_date' => $expense->txn_date?->toDateString(), // @pest-mutate-ignore QBO payload field mapping
            'payment_type' => $expense->payment_type->value, // @pest-mutate-ignore QBO payload field mapping
            'payment_account_ref' => $expense->payment_account_ref, // @pest-mutate-ignore QBO payload field mapping
            'expense_account_ref' => $expense->expense_account_ref, // @pest-mutate-ignore QBO payload field mapping
            'description' => $expense->description, // @pest-mutate-ignore QBO payload field mapping
            'is_billable' => $expense->is_billable, // @pest-mutate-ignore QBO payload field mapping
        ];

        if ($expense->vendor_ref) { // @pest-mutate-ignore QBO payload field mapping
            $payload['vendor_ref'] = $expense->vendor_ref; // @pest-mutate-ignore QBO payload field mapping
        }

        if ($expense->customer_ref) { // @pest-mutate-ignore QBO payload field mapping
            $payload['customer_ref'] = $expense->customer_ref; // @pest-mutate-ignore QBO payload field mapping
        }

        if ($expense->project_ref) { // @pest-mutate-ignore QBO payload field mapping
            $payload['project_ref'] = $expense->project_ref; // @pest-mutate-ignore QBO payload field mapping
        }

        return $payload;
    }
}
