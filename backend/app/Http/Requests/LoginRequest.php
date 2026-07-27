<?php

/**
 * Validates payloads for Sanctum session login.
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for POST /api/login payloads.
 */
class LoginRequest extends FormRequest
{
    /**
     * Allows unauthenticated login requests.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for credential sign-in.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
