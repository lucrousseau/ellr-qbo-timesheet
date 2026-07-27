<?php

namespace App\Enums;

/**
 * Stable JSON error codes consumed by the frontend (`getApiErrorMessage`).
 */
enum ApiErrorCode: string
{
    case RegistrationDisabled = 'registration_disabled';
    case QuickBooksNotConnected = 'quickbooks_not_connected';
    case QuickBooksExpired = 'quickbooks_expired';
    case QuickBooksBusy = 'quickbooks_busy';
    case QboEmployeeNotConfigured = 'qbo_employee_not_configured';
}
