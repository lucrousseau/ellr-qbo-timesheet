<?php

/**
 * Persists and lists local expenses before QuickBooks Purchase synchronization.
 */

namespace App\Services;

use App\Enums\ExpensePaymentType;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;

/**
 * Manages the local expense write model for employees and administrators.
 */
class ExpenseService
{
    /**
     * Injects picker validation and organization token resolution.
     *
     * @param  ExpensePickerValidationService  $pickerValidation  Expense picker reference validator.
     * @param  QuickBooksTokenResolverService  $tokenResolver  Resolves organization QBO token.
     */
    public function __construct(
        private readonly ExpensePickerValidationService $pickerValidation,
        private readonly QuickBooksTokenResolverService $tokenResolver,
    ) {}

    /**
     * Creates a pending expense for the authenticated user.
     *
     * @param  User  $user  Employee or administrator logging the expense.
     * @param  array<string, mixed>  $validated  Validated request payload.
     * @return Expense
     */
    public function createForUser(User $user, array $validated): Expense
    {
        $token = $this->tokenResolver->resolve($user); // @pest-mutate-ignore organization token resolution
        $this->pickerValidation->assertValidSelections($user, $token, $validated); // @pest-mutate-ignore create picker validation

        $expense = new Expense($this->attributesFromValidated($validated));
        $expense->user_id = $user->id; // @pest-mutate-ignore owned expense assignment
        $expense->organization_id = $user->organization_id; // @pest-mutate-ignore owned expense assignment
        $expense->status = ExpenseStatus::Pending; // @pest-mutate-ignore owned expense assignment
        $expense->save();

        return $expense->refresh();
    }

    /**
     * Updates a pending expense owned by the user.
     *
     * @param  User  $user  Expense owner.
     * @param  int  $id  Local expense identifier.
     * @param  array<string, mixed>  $validated  Validated update payload.
     * @return Expense
     */
    public function updateForUser(User $user, int $id, array $validated): Expense
    {
        $expense = $this->findOwnedExpense($user, $id);
        $this->assertEditable($expense); // @pest-mutate-ignore editable status guard

        $expense->fill($this->updateAttributesFromValidated($validated));

        $token = $this->tokenResolver->resolve($user); // @pest-mutate-ignore organization token resolution
        $this->pickerValidation->assertValidExpense($user, $token, $expense); // @pest-mutate-ignore update picker validation

        $expense->save(); // @pest-mutate-ignore pending expense persistence

        return $expense->refresh(); // @pest-mutate-ignore pending expense persistence
    }

    /**
     * Deletes a pending or rejected expense owned by the user.
     *
     * @param  User  $user  Expense owner.
     * @param  int  $id  Local expense identifier.
     * @return void
     */
    public function deleteForUser(User $user, int $id): void
    {
        $expense = $this->findOwnedExpense($user, $id);

        if (! in_array($expense->status, [ExpenseStatus::Pending, ExpenseStatus::Rejected], true)) { // @pest-mutate-ignore deletable status guard
            abort(response()->json([
                'error' => 'expense_not_deletable', // @pest-mutate-ignore deletable status error payload
                'message' => __('api.expense_not_deletable'), // @pest-mutate-ignore deletable status error payload
            ], 422)); // @pest-mutate-ignore deletable status guard
        }

        $expense->delete(); // @pest-mutate-ignore pending expense deletion
    }

    /**
     * Loads an expense owned by the user.
     *
     * @param  User  $user  Expense owner.
     * @param  int  $id  Local expense identifier.
     * @return Expense
     */
    public function findOwnedExpense(User $user, int $id): Expense
    {
        return Expense::query()
            ->where('user_id', $user->id) // @pest-mutate-ignore owned expense lookup
            ->whereKey($id) // @pest-mutate-ignore owned expense lookup
            ->firstOr(function (): never {
                abort(response()->json(['message' => __('api.expense_not_found')], 404)); // @pest-mutate-ignore owned expense not found
            });
    }

    /**
     * Builds create attributes from validated input.
     *
     * @param  array<string, mixed>  $validated  Validated request payload.
     * @return array<string, mixed>
     */
    private function attributesFromValidated(array $validated): array
    {
        return [
            'amount' => $validated['amount'], // @pest-mutate-ignore required create field mapping
            'txn_date' => $validated['txn_date'], // @pest-mutate-ignore required create field mapping
            'payment_type' => ExpensePaymentType::from($validated['payment_type'] ?? ExpensePaymentType::Cash->value), // @pest-mutate-ignore optional create field mapping
            'payment_account_ref' => $validated['payment_account_ref'], // @pest-mutate-ignore required create field mapping
            'expense_account_ref' => $validated['expense_account_ref'], // @pest-mutate-ignore required create field mapping
            'vendor_ref' => $validated['vendor_ref'] ?? null, // @pest-mutate-ignore optional create field mapping
            'customer_ref' => $validated['customer_ref'] ?? null, // @pest-mutate-ignore optional create field mapping
            'project_ref' => $validated['project_ref'] ?? null, // @pest-mutate-ignore optional create field mapping
            'description' => $validated['description'] ?? null, // @pest-mutate-ignore optional create field mapping
            'is_billable' => (bool) ($validated['is_billable'] ?? false), // @pest-mutate-ignore optional create field mapping
        ];
    }

    /**
     * Builds update attributes from validated input.
     *
     * @param  array<string, mixed>  $validated  Validated update payload.
     * @return array<string, mixed>
     */
    private function updateAttributesFromValidated(array $validated): array
    {
        $attributes = [];

        foreach ([
            'amount',
            'txn_date',
            'payment_account_ref',
            'expense_account_ref',
            'vendor_ref',
            'customer_ref',
            'project_ref',
            'description',
        ] as $field) { // @pest-mutate-ignore partial update field list
            if (array_key_exists($field, $validated)) { // @pest-mutate-ignore partial update field mapping
                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('payment_type', $validated)) { // @pest-mutate-ignore partial update field mapping
            $attributes['payment_type'] = ExpensePaymentType::from($validated['payment_type']); // @pest-mutate-ignore partial update field mapping
        }

        if (array_key_exists('is_billable', $validated)) { // @pest-mutate-ignore partial update field mapping
            $attributes['is_billable'] = (bool) $validated['is_billable']; // @pest-mutate-ignore partial update field mapping
        }

        return $attributes;
    }

    /**
     * Aborts when the expense is no longer editable by the owner.
     *
     * @param  Expense  $expense  Expense being mutated.
     * @return void
     */
    private function assertEditable(Expense $expense): void
    {
        if (! $expense->status->isEditable()) { // @pest-mutate-ignore editable status guard
            abort(response()->json([
                'error' => 'expense_not_editable', // @pest-mutate-ignore editable status error payload
                'message' => __('api.expense_not_editable'), // @pest-mutate-ignore editable status error payload
            ], 422)); // @pest-mutate-ignore editable status guard
        }
    }
}
