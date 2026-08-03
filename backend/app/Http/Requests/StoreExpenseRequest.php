<?php

/**
 * Validates payloads for creating local expenses.
 */

namespace App\Http\Requests;

use App\Enums\ExpensePaymentType;
use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request rules for POST /api/expenses payloads.
 */
class StoreExpenseRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for expense creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'txn_date' => ['required', 'date'],
            'payment_type' => ['sometimes', 'string', Rule::in(ExpensePaymentType::values())],
            'payment_account_ref' => ['required', 'string', 'max:255'],
            'expense_account_ref' => ['required', 'string', 'max:255'],
            'vendor_ref' => ['nullable', 'string', 'max:255'], // @pest-mutate-ignore declarative validation rules
            'customer_ref' => ['nullable', 'string', 'max:255'], // @pest-mutate-ignore declarative validation rules
            'project_ref' => ['nullable', 'string', 'max:255'], // @pest-mutate-ignore declarative validation rules
            'description' => ['nullable', 'string', 'max:4000'], // @pest-mutate-ignore declarative validation rules
            'is_billable' => ['sometimes', 'boolean'],
        ];
    }
}
