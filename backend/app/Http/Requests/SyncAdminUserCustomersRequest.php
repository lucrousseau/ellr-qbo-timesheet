<?php

/**
 * Validates administrator customer assignment payloads.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates QuickBooks customer identifiers assigned to a timesheet user.
 */
class SyncAdminUserCustomersRequest extends FormRequest
{
    /**
     * Allows only administrators to sync customer assignments.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * Defines validation rules for customer assignment sync.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_refs' => ['present', 'array'],
            'customer_refs.*' => ['string', 'max:255'],
            'all_customers_access' => ['required', 'boolean'],
        ];
    }
}
