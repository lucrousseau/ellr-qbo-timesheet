<?php

/**
 * Validates rejection payloads for pending expenses.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for POST /api/expense-approvals/{id}/reject.
 */
class RejectExpenseRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for expense rejection.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
