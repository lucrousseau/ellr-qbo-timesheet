<?php

/**
 * Shared QuickBooks data access dependencies for employee-scoped services.
 */

namespace App\Support;

use App\Services\QboEmployeeAuthorizationService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;

/**
 * Injects QuickBooks SDK access, employee authorization, and API error formatting.
 */
trait UsesQuickBooksEmployeeScope
{
    /**
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QboEmployeeAuthorizationService  $employeeAuthorization  QBO employee ownership checks.
     * @param  QuickBooksApiErrorFormatterService  $apiErrors  QuickBooks API error JSON formatter.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QboEmployeeAuthorizationService $employeeAuthorization,
        private readonly QuickBooksApiErrorFormatterService $apiErrors,
    ) {}
}
