<?php

/**
 * Validates payloads for administrator-created QuickBooks projects.
 */

namespace App\Http\Requests;

use App\Http\Concerns\AllowsAuthenticatedApiUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request rules for POST /api/admin/quickbooks/projects payloads.
 */
class StoreAdminQboProjectRequest extends FormRequest
{
    use AllowsAuthenticatedApiUser;

    /**
     * Validation rules for QuickBooks project creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_ref' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:100'],
        ];
    }
}
