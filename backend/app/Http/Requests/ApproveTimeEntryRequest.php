<?php

/**
 * Validates payloads for approving pending time entries.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for POST /api/time-entry-approvals/{id}/approve payloads.
 */
class ApproveTimeEntryRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for time entry approval.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_for_qbo' => ['sometimes', 'boolean'], // @pest-mutate-ignore declarative validation rules
        ];
    }

    /**
     * Whether matching approved entries should coalesce into one QuickBooks activity.
     *
     * @return bool
     */
    public function groupForQbo(): bool
    {
        return $this->boolean('group_for_qbo', false); // @pest-mutate-ignore opt-in grouping default
    }
}
