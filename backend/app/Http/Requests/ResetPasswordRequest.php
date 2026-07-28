<?php

/**
 * Validates payloads for password reset submissions.
 */

namespace App\Http\Requests;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for POST /api/reset-password payloads.
 */
class ResetPasswordRequest extends FormRequest
{
    /**
     * Allows unauthenticated password reset submissions.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for reset-password payloads.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => PasswordRules::newPassword(),
        ];
    }
}
