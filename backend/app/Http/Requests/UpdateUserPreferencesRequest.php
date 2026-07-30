<?php

/**
 * Validates payloads for updating authenticated user preferences.
 */

namespace App\Http\Requests;

use App\Enums\UserLocale;
use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request rules for PATCH /api/user/preferences payloads.
 */
class UpdateUserPreferencesRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for locale and timezone preferences.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(UserLocale::values())],
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
        ];
    }

    /**
     * Normalizes blank timezone values to null.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('timezone')) {
            return;
        }

        $timezone = $this->input('timezone');

        if ($timezone === null || (is_string($timezone) && trim($timezone) === '')) {
            $this->merge(['timezone' => null]);
        }
    }
}
