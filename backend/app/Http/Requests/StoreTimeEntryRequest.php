<?php

/**
 * Validates payloads for creating local time entries.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for POST /api/time-entries payloads.
 */
class StoreTimeEntryRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for time entry creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_ref' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'project_ref' => ['nullable', 'string', 'max:255'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'item_ref' => ['nullable', 'string', 'max:255'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'description' => ['nullable', 'string', 'max:4000'],
            'is_billable' => ['sometimes', 'boolean'],
        ];
    }
}
