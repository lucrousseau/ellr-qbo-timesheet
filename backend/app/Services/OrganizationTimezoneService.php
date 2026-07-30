<?php

/**
 * Resolves and syncs tenant company timezone preferences.
 */

namespace App\Services;

use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Support\TimezoneIdentifier;

/**
 * Stores QuickBooks company timezone on organizations and resolves it for QBO math.
 */
class OrganizationTimezoneService
{
    /**
     * Injects the QuickBooks company profile reader.
     *
     * @param  QuickBooksCompanyInfoService  $companyInfo  QuickBooks CompanyInfo reader.
     */
    public function __construct(
        private readonly QuickBooksCompanyInfoService $companyInfo,
    ) {}

    /**
     * Persists the QuickBooks company timezone on the tenant organization.
     *
     * @param  Organization  $organization  Tenant organization.
     * @param  QuickBooksToken  $token  Organization OAuth token.
     * @return void
     */
    public function syncFromQuickBooks(Organization $organization, QuickBooksToken $token): void
    {
        $timezone = $this->companyInfo->fetchDefaultTimezone($token);

        if ($timezone === null) {
            return;
        }

        $organization->update(['company_timezone' => $timezone]);
    }

    /**
     * Syncs the company timezone from QuickBooks when the tenant has no stored value.
     *
     * @param  Organization  $organization  Tenant organization.
     * @param  QuickBooksToken  $token  Organization OAuth token.
     * @return void
     */
    public function syncIfMissing(Organization $organization, QuickBooksToken $token): void
    {
        if (TimezoneIdentifier::normalize($organization->company_timezone) !== null) {
            return;
        }

        $this->syncFromQuickBooks($organization, $token);
    }

    /**
     * Resolves the company timezone for a QuickBooks realm identifier.
     *
     * @param  string|null  $realmId  QuickBooks company realm identifier.
     * @return string
     */
    public function forRealm(?string $realmId): string
    {
        if (is_string($realmId) && $realmId !== '') {
            $organization = Organization::query()
                ->where('realm_id', $realmId)
                ->first();

            $timezone = TimezoneIdentifier::normalize($organization?->company_timezone);

            if ($timezone !== null) {
                return $timezone;
            }
        }

        return $this->fallbackTimezone();
    }

    /**
     * Resolves the company timezone for a tenant organization.
     *
     * @param  Organization|null  $organization  Tenant organization.
     * @return string
     */
    public function forOrganization(?Organization $organization): string
    {
        $timezone = TimezoneIdentifier::normalize($organization?->company_timezone);

        return $timezone ?? $this->fallbackTimezone();
    }

    /**
     * Returns the deployment fallback timezone when no organization value exists.
     *
     * @return string
     */
    public function fallbackTimezone(): string
    {
        return TimezoneIdentifier::normalize((string) config('quickbooks.company_timezone', 'UTC'))
            ?? 'UTC';
    }
}
