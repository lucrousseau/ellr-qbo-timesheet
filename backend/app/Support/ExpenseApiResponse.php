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
        $expense->loadMissing(['user', 'reviewedBy']); // @pest-mutate-ignore API resource relation preload
        $labels ??= [
            'payment_account_name' => null,
            'expense_account_name' => null,
            'vendor_name' => null,
            'customer_name' => null,
            'project_name' => null,
        ];

        return [
            'id' => $expense->id, // @pest-mutate-ignore API resource field mapping
            'user_id' => $expense->user_id, // @pest-mutate-ignore API resource field mapping
            'employee_name' => $expense->user?->name, // @pest-mutate-ignore API resource field mapping
            'amount' => (string) $expense->amount, // @pest-mutate-ignore API resource field mapping
            'txn_date' => $expense->txn_date?->toDateString(), // @pest-mutate-ignore API resource field mapping
            'payment_type' => $expense->payment_type->value, // @pest-mutate-ignore API resource field mapping
            'payment_account_ref' => $expense->payment_account_ref, // @pest-mutate-ignore API resource field mapping
            'payment_account_name' => $labels['payment_account_name'] ?? null, // @pest-mutate-ignore API resource field mapping
            'expense_account_ref' => $expense->expense_account_ref, // @pest-mutate-ignore API resource field mapping
            'expense_account_name' => $labels['expense_account_name'] ?? null, // @pest-mutate-ignore API resource field mapping
            'vendor_ref' => $expense->vendor_ref, // @pest-mutate-ignore API resource field mapping
            'vendor_name' => $labels['vendor_name'] ?? null, // @pest-mutate-ignore API resource field mapping
            'customer_ref' => $expense->customer_ref, // @pest-mutate-ignore API resource field mapping
            'customer_name' => $labels['customer_name'] ?? null, // @pest-mutate-ignore API resource field mapping
            'project_ref' => $expense->project_ref, // @pest-mutate-ignore API resource field mapping
            'project_name' => $labels['project_name'] ?? null, // @pest-mutate-ignore API resource field mapping
            'description' => $expense->description, // @pest-mutate-ignore API resource field mapping
            'is_billable' => $expense->is_billable, // @pest-mutate-ignore API resource field mapping
            'status' => $expense->status->value, // @pest-mutate-ignore API resource field mapping
            'reviewed_by_id' => $expense->reviewed_by_id, // @pest-mutate-ignore API resource field mapping
            'reviewed_by_name' => $expense->reviewedBy?->name, // @pest-mutate-ignore API resource field mapping
            'reviewed_at' => $expense->reviewed_at?->toIso8601String(), // @pest-mutate-ignore API resource field mapping
            'rejection_reason' => $expense->rejection_reason, // @pest-mutate-ignore API resource field mapping
            'qbo_id' => $expense->qbo_id, // @pest-mutate-ignore API resource field mapping
            'created_at' => $expense->created_at?->toIso8601String(), // @pest-mutate-ignore API resource field mapping
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
            ->map(fn (Expense $expense): array => self::resource($expense)) // @pest-mutate-ignore API resource collection mapping
            ->values()
            ->all();
    }
}
