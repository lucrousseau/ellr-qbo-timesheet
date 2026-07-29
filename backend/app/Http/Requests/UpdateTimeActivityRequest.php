<?php

/**
 * Validates payloads for updating QuickBooks time activities.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request rules for PATCH /api/time-activities/{id} payloads.
 *
 * Partial time updates also validate resolved start/end pairs in TimeActivityService.
 */
class UpdateTimeActivityRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for time activity updates.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_time' => ['sometimes', 'date'],
            'end_time' => [
                'sometimes',
                'date',
                Rule::when($this->filled('start_time'), 'after:start_time'),
            ],
            'description' => ['nullable', 'string', 'max:4000'],
            'is_billable' => ['sometimes', 'boolean'],
        ];
    }
}
