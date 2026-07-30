<?php

/**
 * Validates payloads for super-administrator organization updates.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for PATCH /api/super-admin/organizations/{organization} payloads.
 */
class UpdateSuperAdminOrganizationRequest extends FormRequest
{
    /**
     * Allows authenticated super-administrator requests only.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * Validation rules for tenant organization updates.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
