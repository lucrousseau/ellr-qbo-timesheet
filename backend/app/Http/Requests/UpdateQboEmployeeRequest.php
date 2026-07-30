<?php

/**
 * Validates payloads for linking a user account to a QuickBooks employee.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use App\Http\Concerns\ScopesQboEmployeeRefUniqueness;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for PATCH /api/user/qbo-employee payloads.
 */
class UpdateQboEmployeeRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;
    use ScopesQboEmployeeRefUniqueness;

    /**
     * Validation rules for QBO employee mapping.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $ignoreUserId = $target instanceof User ? $target->id : $this->user()?->id;

        return [
            'qbo_employee_ref' => [
                'required',
                'string',
                'max:255',
                $this->uniqueQboEmployeeRefForOrganization($ignoreUserId),
            ],
            'qbo_employee_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
