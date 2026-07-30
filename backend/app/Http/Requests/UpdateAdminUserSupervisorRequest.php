<?php

/**
 * Validates administrator supervisor assignment payloads.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for PATCH /api/admin/users/{user}/supervisor payloads.
 */
class UpdateAdminUserSupervisorRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for supervisor assignment.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Returns the supervisor user id or null to clear the assignment.
     *
     * @return int|null
     */
    public function supervisorId(): ?int
    {
        if (! $this->has('supervisor_id')) {
            return null;
        }

        $value = $this->validated('supervisor_id');

        return $value === null ? null : (int) $value;
    }
}
