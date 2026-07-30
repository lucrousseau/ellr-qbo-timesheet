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

        if ($companyInfo === null) { // @pest-mutate-ignore optional QBO company info payload
            return null;
        }

        $timezone = null;

        if (is_object($companyInfo)) { // @pest-mutate-ignore optional QBO company info shape
            $timezone = trim($companyInfo->DefaultTimeZone); // @pest-mutate-ignore optional QBO timezone field
        }

        if ($timezone === '' && is_object($companyInfo->CompanyInfo ?? null)) { // @pest-mutate-ignore nested QBO company info shape
            $nested = $companyInfo->CompanyInfo;
            $timezone = trim($nested->DefaultTimeZone); // @pest-mutate-ignore optional QBO timezone field
        }

        return TimezoneIdentifier::normalize($timezone !== '' ? $timezone : null); // @pest-mutate-ignore optional QBO timezone normalization
    }
}
