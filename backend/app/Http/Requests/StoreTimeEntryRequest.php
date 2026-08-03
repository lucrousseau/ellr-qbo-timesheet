<?php

/**
 * Validates payloads for creating local time entries.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use App\Support\TicketFieldRules;
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
            'customer_ref' => ['nullable', 'string', 'max:255'], // @pest-mutate-ignore declarative validation rules
            'project_ref' => ['nullable', 'string', 'max:255'], // @pest-mutate-ignore declarative validation rules
            'item_ref' => ['nullable', 'string', 'max:255'], // @pest-mutate-ignore declarative validation rules
            'start_time' => ['required', 'date'], // @pest-mutate-ignore declarative validation rules
            'end_time' => ['required', 'date', 'after:start_time'], // @pest-mutate-ignore declarative validation rules
            'description' => ['nullable', 'string', 'max:4000'], // @pest-mutate-ignore declarative validation rules
            ...TicketFieldRules::forCreate(), // @pest-mutate-ignore shared ticket validation rules
            'is_billable' => ['sometimes', 'boolean'],
        ];
    }
}
