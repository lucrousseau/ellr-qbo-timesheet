<?php

/**
 * Validates payloads for authenticated email address changes.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request rules for PATCH /api/user/email payloads.
 */
class ChangeEmailRequest extends FormRequest
{
    /**
     * Allows signed-in users to change their own email address.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for change-email payloads.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'current_password' => ['required', 'string', 'current_password'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
                Rule::notIn([(string) ($user->email ?? '')]),
            ],
        ];
    }
}
