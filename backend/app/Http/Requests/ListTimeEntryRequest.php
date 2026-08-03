<?php

/**
 * Validates query parameters for listing local time entries.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for GET /api/time-entries list queries.
 */
class ListTimeEntryRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for paginated time entry lists.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_position' => ['sometimes', 'integer', 'min:1'], // @pest-mutate-ignore declarative validation rules
            'max_results' => ['sometimes', 'integer', 'min:1', 'max:100'], // @pest-mutate-ignore list pagination cap
            'refresh' => ['sometimes', 'boolean'], // @pest-mutate-ignore declarative validation rules
        ];
    }

    /**
     * Returns the one-based pagination offset.
     *
     * @return int
     */
    public function listStartPosition(): int
    {
        return (int) ($this->validated('start_position') ?? 1); // @pest-mutate-ignore list pagination default
    }

    /**
     * Returns the maximum number of rows per page.
     *
     * @return int
     */
    public function listMaxResults(): int
    {
        return (int) ($this->validated('max_results') ?? 25); // @pest-mutate-ignore list pagination default
    }

    /**
     * Returns whether the client requested a fresh QuickBooks snapshot reconcile.
     *
     * @return bool
     */
    public function listRefresh(): bool
    {
        return $this->boolean('refresh');
    }
}
