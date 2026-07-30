<?php

/**
 * Validates payloads for public user registration.
 */

namespace App\Http\Requests;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for POST /api/register payloads.
 */
class RegisterRequest extends FormRequest
{
    /**
     * Allows unauthenticated registration requests.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for user registration.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'organization_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => PasswordRules::newPassword(),
        ];
    }
}
