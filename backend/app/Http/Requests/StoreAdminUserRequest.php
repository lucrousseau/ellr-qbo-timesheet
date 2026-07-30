<?php

/**
 * Validates payloads for administrator-provisioned timesheet users.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use App\Http\Concerns\ScopesQboEmployeeRefUniqueness;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for POST /api/admin/users payloads.
 */
class StoreAdminUserRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;
    use ScopesQboEmployeeRefUniqueness;

    /**
     * Validation rules for timesheet user provisioning.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qbo_employee_ref' => [
                'required',
                'string',
                'max:255',
                $this->uniqueQboEmployeeRefForOrganization(),
            ],
        ];
    }
}
