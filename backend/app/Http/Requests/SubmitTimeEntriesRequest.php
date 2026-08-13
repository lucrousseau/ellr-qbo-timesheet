<?php

/**
 * Validates bulk submit payloads for draft time entries.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for POST /api/time-entries/submit payloads.
 */
class SubmitTimeEntriesRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for bulk submit.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
