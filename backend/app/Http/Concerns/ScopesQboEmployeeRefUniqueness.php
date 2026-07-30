<?php

/**
 * Shared validation for QuickBooks employee refs scoped per tenant organization.
 */

namespace App\Http\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Builds unique rules for {@see User::$qbo_employee_ref} within the actor organization.
 */
trait ScopesQboEmployeeRefUniqueness
{
    /**
     * Unique rule for QBO employee refs within the authenticated user's organization.
     *
     * @param  int|null  $ignoreUserId  User ID to exclude from the uniqueness check.
     * @return Unique
     */
    protected function uniqueQboEmployeeRefForOrganization(?int $ignoreUserId = null): Unique
    {
        $organizationId = $this->user()->organization_id ?? -1;

        $rule = Rule::unique('users', 'qbo_employee_ref')
            ->where('organization_id', $organizationId);

        if ($ignoreUserId !== null) {
            $rule->ignore($ignoreUserId);
        }

        return $rule;
    }
}
