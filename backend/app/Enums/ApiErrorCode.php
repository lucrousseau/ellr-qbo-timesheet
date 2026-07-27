<?php

/**
 * Stable API error codes returned in JSON responses for frontend mapping.
 */

namespace App\Enums;

/**
 * Backed enum of machine-readable API error identifiers.
 */
enum ApiErrorCode: string
{
    case RegistrationDisabled = 'registration_disabled';
    case QuickBooksNotConnected = 'quickbooks_not_connected';
    case QuickBooksExpired = 'quickbooks_expired';
    case QuickBooksBusy = 'quickbooks_busy';
    case QboEmployeeNotConfigured = 'qbo_employee_not_configured';
}
