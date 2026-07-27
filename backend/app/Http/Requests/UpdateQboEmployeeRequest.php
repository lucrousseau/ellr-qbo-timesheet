<?php

/**
 * Validates payloads for linking a user account to a QuickBooks employee.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for PATCH /api/user/qbo-employee payloads.
 */
class UpdateQboEmployeeRequest extends FormRequest
{
    /**
     * Allows any authenticated user (Sanctum check runs upstream).
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for QBO employee mapping.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qbo_employee_ref' => ['required', 'string', 'max:255'],
            'qbo_employee_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
