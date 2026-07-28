<?php

/**
 * Validates payloads for updating the authenticated user locale preference.
 */

namespace App\Http\Requests;

use App\Enums\UserLocale;
use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request rules for PATCH /api/user/locale payloads.
 */
class UpdateUserLocaleRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for the locale preference.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(UserLocale::values())],
        ];
    }
}
