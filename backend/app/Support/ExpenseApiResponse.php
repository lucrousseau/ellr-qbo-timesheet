<?php

/**
 * Serializes local expenses for JSON API responses.
 */

namespace App\Support;

use App\Models\Expense;
use Illuminate\Support\Collection;

/**
 * Builds expense payloads exposed to timesheet and admin clients.
 */
class ExpenseApiResponse
{
    /**
     * Maps an expense model to an API-friendly array.
     *
     * @param  Expense  $expense  Local expense instance.
     * @param  array<string, string|null>|null  $labels  Resolved picker labels keyed by field name.
     * @return array<string, mixed>
     */
    public static function resource(Expense $expense, ?array $labels = null): array
    {
        $expense->loadMissing(['user', 'reviewedBy']);
        $labels ??= [
            'payment_account_name' => null,
            'expense_account_name' => null,
            'vendor_name' => null,
            'customer_name' => null,
            'project_name' => null,
        ];

        return [
            'id' => $expense->id,
            'user_id' => $expense->user_id,
            'employee_name' => $expense->user?->name,
            'amount' => (string) $expense->amount,
            'txn_date' => $expense->txn_date?->toDateString(),
            'payment_type' => $expense->payment_type->value,
            'payment_account_ref' => $expense->payment_account_ref,
            'payment_account_name' => $labels['payment_account_name'] ?? null,
            'expense_account_ref' => $expense->expense_account_ref,
            'expense_account_name' => $labels['expense_account_name'] ?? null,
            'vendor_ref' => $expense->vendor_ref,
            'vendor_name' => $labels['vendor_name'] ?? null,
            'customer_ref' => $expense->customer_ref,
            'customer_name' => $labels['customer_name'] ?? null,
            'project_ref' => $expense->project_ref,
            'project_name' => $labels['project_name'] ?? null,
            'description' => $expense->description,
            'is_billable' => $expense->is_billable,
            'status' => $expense->status->value,
            'reviewed_by_id' => $expense->reviewed_by_id,
            'reviewed_by_name' => $expense->reviewedBy?->name,
            'reviewed_at' => $expense->reviewed_at?->toIso8601String(),
            'rejection_reason' => $expense->rejection_reason,
            'qbo_id' => $expense->qbo_id,
            'created_at' => $expense->created_at?->toIso8601String(),
        ];
    }

    /**
     * Maps a collection of expenses to API payloads.
     *
     * @param  Collection<int, Expense>  $expenses  Expense collection.
     * @return list<array<string, mixed>>
     */
    public static function collection(Collection $expenses): array
    {
        return $expenses
            ->map(fn (Expense $expense): array => self::resource($expense))
            ->values()
            ->all();
    }
}
