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
        $token = $this->tokenResolver->resolve($user);
        $this->pickerValidation->assertValidSelections($user, $token, $validated);

        $expense = new Expense($this->attributesFromValidated($validated));
        $expense->user_id = $user->id;
        $expense->organization_id = $user->organization_id;
        $expense->status = ExpenseStatus::Pending;
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
        $this->assertEditable($expense);

        $expense->fill($this->updateAttributesFromValidated($validated));

        $token = $this->tokenResolver->resolve($user);
        $this->pickerValidation->assertValidExpense($user, $token, $expense);

        $expense->save();

        return $expense->refresh();
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

        if (! in_array($expense->status, [ExpenseStatus::Pending, ExpenseStatus::Rejected], true)) {
            abort(response()->json([
                'error' => 'expense_not_deletable',
                'message' => __('api.expense_not_deletable'),
            ], 422));
        }

        $expense->delete();
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
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOr(function (): never {
                abort(response()->json(['message' => __('api.expense_not_found')], 404));
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
            'amount' => $validated['amount'],
            'txn_date' => $validated['txn_date'],
            'payment_type' => ExpensePaymentType::from($validated['payment_type'] ?? ExpensePaymentType::Cash->value),
            'payment_account_ref' => $validated['payment_account_ref'],
            'expense_account_ref' => $validated['expense_account_ref'],
            'vendor_ref' => $validated['vendor_ref'] ?? null,
            'customer_ref' => $validated['customer_ref'] ?? null,
            'project_ref' => $validated['project_ref'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_billable' => (bool) ($validated['is_billable'] ?? false),
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
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('payment_type', $validated)) {
            $attributes['payment_type'] = ExpensePaymentType::from($validated['payment_type']);
        }

        if (array_key_exists('is_billable', $validated)) {
            $attributes['is_billable'] = (bool) $validated['is_billable'];
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
        if (! $expense->status->isEditable()) {
            abort(response()->json([
                'error' => 'expense_not_editable',
                'message' => __('api.expense_not_editable'),
            ], 422));
        }
    }
}
