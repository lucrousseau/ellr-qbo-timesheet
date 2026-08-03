<?php

/**
 * Validates payloads for searching external tickets.
 */

namespace App\Http\Requests;

use App\Enums\TicketSource;
use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request rules for GET /api/integrations/tickets search.
 */
class SearchTicketsRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for ticket search.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:255'],
            'provider' => [
                'nullable',
                'string',
                Rule::in([TicketSource::Jira->value, TicketSource::Linear->value]),
            ],
        ];
    }
}
