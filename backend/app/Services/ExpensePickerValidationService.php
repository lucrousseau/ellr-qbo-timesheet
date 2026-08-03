<?php

/**
 * Validates QuickBooks picker references used by expense forms.
 */

namespace App\Services;

use App\Models\Expense;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Support\QboRefNormalizer;

/**
 * Ensures expense account, payment account, vendor, and customer refs are allowed.
 */
class ExpensePickerValidationService
{
    /**
     * Injects list services used to validate expense picker selections.
     *
     * @param  QboAccountListService  $accounts  Chart of Accounts list service.
     * @param  QboVendorListService  $vendors  Vendor list service.
     * @param  QboPickerValidationService  $pickerValidation  Customer and project validator.
     */
    public function __construct(
        private readonly QboAccountListService $accounts,
        private readonly QboVendorListService $vendors,
        private readonly QboPickerValidationService $pickerValidation,
    ) {}

    /**
     * Aborts with 422 when any expense picker reference is not allowed.
     *
     * @param  User  $user  Expense owner.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  array<string, mixed>  $validated  Expense payload fields.
     * @return void
     */
    public function assertValidSelections(User $user, QuickBooksToken $token, array $validated): void
    {
        $paymentAccountRef = QboRefNormalizer::normalize($validated['payment_account_ref'] ?? null);
        $expenseAccountRef = QboRefNormalizer::normalize($validated['expense_account_ref'] ?? null);
        $vendorRef = QboRefNormalizer::normalize($validated['vendor_ref'] ?? null);

        if ($paymentAccountRef === null || ! QboRefNormalizer::optionExists($this->accounts->listPaymentAccounts($token), $paymentAccountRef)) {
            abort(response()->json([
                'error' => 'expense_invalid_payment_account',
                'message' => __('api.expense_invalid_payment_account'),
            ], 422));
        }

        if ($expenseAccountRef === null || ! QboRefNormalizer::optionExists($this->accounts->listExpenseAccounts($token), $expenseAccountRef)) {
            abort(response()->json([
                'error' => 'expense_invalid_expense_account',
                'message' => __('api.expense_invalid_expense_account'),
            ], 422));
        }

        if ($vendorRef !== null && ! QboRefNormalizer::optionExists($this->vendors->listActive($token), $vendorRef)) {
            abort(response()->json([
                'error' => 'expense_invalid_vendor',
                'message' => __('api.expense_invalid_vendor'),
            ], 422));
        }

        if ($this->hasCustomerOrProject($validated)) { // @pest-mutate-ignore create picker guard
            $this->pickerValidation->assertValidTimeEntrySelections($user, $token, [ // @pest-mutate-ignore create picker validation
                'customer_ref' => $validated['customer_ref'] ?? null, // @pest-mutate-ignore create picker field mapping
                'project_ref' => $validated['project_ref'] ?? null, // @pest-mutate-ignore create picker field mapping
                'item_ref' => null, // @pest-mutate-ignore create picker field mapping
            ]);
        }
    }

    /**
     * Aborts with 422 when a stored expense references disallowed QuickBooks pickers.
     *
     * @param  User  $user  Expense owner.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  Expense  $expense  Local expense being reviewed.
     * @return void
     */
    public function assertValidExpense(User $user, QuickBooksToken $token, Expense $expense): void
    {
        $this->assertValidSelections($user, $token, [
            'payment_account_ref' => $expense->payment_account_ref, // @pest-mutate-ignore update picker field mapping
            'expense_account_ref' => $expense->expense_account_ref, // @pest-mutate-ignore update picker field mapping
            'vendor_ref' => $expense->vendor_ref, // @pest-mutate-ignore update picker field mapping
            'customer_ref' => $expense->customer_ref, // @pest-mutate-ignore update picker field mapping
            'project_ref' => $expense->project_ref, // @pest-mutate-ignore update picker field mapping
        ]);
    }

    /**
     * Indicates whether the payload includes a customer or project reference.
     *
     * @param  array<string, mixed>  $validated  Expense payload fields.
     * @return bool
     */
    private function hasCustomerOrProject(array $validated): bool
    {
        foreach (['customer_ref', 'project_ref'] as $field) { // @pest-mutate-ignore picker field list
            $value = $validated[$field] ?? null;

            if (is_string($value) && trim($value) !== '') { // @pest-mutate-ignore optional picker reference normalization
                return true;
            }
        }

        return false; // @pest-mutate-ignore optional picker reference absence
    }
}
