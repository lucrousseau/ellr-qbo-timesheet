<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for partial update of a QuickBooks time activity.
 */
class UpdateTimeActivityRequest extends FormRequest
{
    /**
     * Allows any authenticated user (Sanctum check runs upstream).
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for time activity updates.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_time' => ['sometimes', 'date'],
            'end_time' => ['sometimes', 'date'],
            'description' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
