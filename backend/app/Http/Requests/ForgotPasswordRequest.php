<?php

/**
 * Validates payloads for password reset link requests.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for POST /api/forgot-password payloads.
 */
class ForgotPasswordRequest extends FormRequest
{
    /**
     * Allows unauthenticated password reset requests.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for forgot-password payloads.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
