<?php

/**
 * Validates payloads for upserting active time tracker state.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for PUT /api/time-tracker payloads.
 */
class UpdateTimeTrackerRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for active timer synchronization.
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
            'service_ref' => ['nullable', 'string', 'max:255'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'is_running' => ['required', 'boolean'],
        ];
    }
}
