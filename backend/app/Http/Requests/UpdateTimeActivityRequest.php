<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
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
