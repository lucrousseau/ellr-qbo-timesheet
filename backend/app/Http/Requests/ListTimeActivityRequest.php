<?php

/**
 * Validates query parameters for listing QuickBooks time activities.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for GET /api/time-activities query parameters.
 */
class ListTimeActivityRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for optional list pagination parameters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxResults = (int) config('quickbooks.time_activities_max_results', 100);

        return [
            'start_position' => ['sometimes', 'integer', 'min:1'],
            'max_results' => ['sometimes', 'integer', 'min:1', 'max:'.$maxResults],
        ];
    }

    /**
     * Returns the validated QuickBooks STARTPOSITION value.
     *
     * @return int
     */
    public function listStartPosition(): int
    {
        return (int) ($this->validated('start_position') ?? 1);
    }

    /**
     * Returns the validated MAXRESULTS cap for the list query.
     *
     * @return int
     */
    public function listMaxResults(): int
    {
        return (int) ($this->validated('max_results') ?? config('quickbooks.time_activities_max_results', 100));
    }
}
