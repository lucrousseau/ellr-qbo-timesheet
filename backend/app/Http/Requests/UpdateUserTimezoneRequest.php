<?php

/**
 * Validates payloads for updating the authenticated user timezone preference.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request rules for PATCH /api/user/timezone payloads.
 */
class UpdateUserTimezoneRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for the timezone preference.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
