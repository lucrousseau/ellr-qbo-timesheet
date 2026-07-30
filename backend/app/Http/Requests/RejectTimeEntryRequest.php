<?php

/**
 * Validates payloads for rejecting pending time entries.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for POST /api/time-entry-approvals/{id}/reject payloads.
 */
class RejectTimeEntryRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for time entry rejection.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
