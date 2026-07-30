<?php

/**
 * Validates payloads for super-administrator organization provisioning.
 */

namespace App\Http\Requests;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for POST /api/super-admin/organizations payloads.
 */
class StoreSuperAdminOrganizationRequest extends FormRequest
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
     * Validation rules for tenant organization provisioning.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => PasswordRules::newPassword(),
        ];
    }
}
