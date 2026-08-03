<?php

/**
 * Validates query parameters for listing local expenses.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for GET /api/expenses list queries.
 */
class ListExpenseRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for paginated expense lists.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_position' => ['sometimes', 'integer', 'min:1'],
            'max_results' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Returns the one-based pagination offset.
     *
     * @return int
     */
    public function listStartPosition(): int
    {
        return (int) ($this->validated('start_position') ?? 1);
    }

    /**
     * Returns the maximum number of rows per page.
     *
     * @return int
     */
    public function listMaxResults(): int
    {
        return (int) ($this->validated('max_results') ?? 25);
    }
}
