<?php

/**
 * Validates payloads for updating pending local time entries.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for PATCH /api/time-entries payloads.
 */
class UpdateTimeEntryRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for time entry updates.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_ref' => ['sometimes', 'nullable', 'string', 'max:255'], // @pest-mutate-ignore declarative validation rules
            'project_ref' => ['sometimes', 'nullable', 'string', 'max:255'], // @pest-mutate-ignore declarative validation rules
            'item_ref' => ['sometimes', 'nullable', 'string', 'max:255'], // @pest-mutate-ignore declarative validation rules
            'start_time' => ['sometimes', 'date'],
            'end_time' => ['sometimes', 'date', 'after:start_time'],
            'description' => ['sometimes', 'nullable', 'string', 'max:4000'], // @pest-mutate-ignore declarative validation rules
            'is_billable' => ['sometimes', 'boolean'],
        ];
    }
}
