<?php

/**
 * Validates payloads for authenticated password changes.
 */

namespace App\Http\Requests;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for PATCH /api/user/password payloads.
 */
class ChangePasswordRequest extends FormRequest
{
    /**
     * Allows signed-in users to change their own password.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for change-password payloads.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => PasswordRules::newPassword(),
        ];
    }
}
