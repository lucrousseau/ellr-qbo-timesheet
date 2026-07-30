<?php

/**
 * Reads QuickBooks company profile fields used by tenant preferences.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Support\TimezoneIdentifier;

/**
 * Fetches company metadata from QuickBooks Online after OAuth connect.
 */
class QuickBooksCompanyInfoService
{
    /**
     * Injects the QuickBooks SDK wrapper.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
    ) {}

    /**
     * Returns the company default timezone from QuickBooks when available.
     *
     * @param  QuickBooksToken  $token  Organization OAuth token.
     * @return string|null
     */
    public function fetchDefaultTimezone(QuickBooksToken $token): ?string
    {
        $dataService = $this->quickBooks->dataService($token);
        $companyInfo = $dataService->getCompanyInfo();

        if ($companyInfo === null) {
            return null;
        }

        $timezone = null;

        if (is_object($companyInfo) && isset($companyInfo->DefaultTimeZone)) {
            $timezone = trim((string) $companyInfo->DefaultTimeZone);
        }

        if (($timezone === null || $timezone === '') && is_object($companyInfo->CompanyInfo ?? null)) {
            $nested = $companyInfo->CompanyInfo;
            $timezone = isset($nested->DefaultTimeZone) ? trim((string) $nested->DefaultTimeZone) : '';
        }

        return TimezoneIdentifier::normalize($timezone !== '' ? $timezone : null);
    }
}
