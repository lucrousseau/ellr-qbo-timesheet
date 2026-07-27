<?php

namespace App\Enums;

enum ApiErrorCode: string
{
    case RegistrationDisabled = 'registration_disabled';
    case QuickBooksNotConnected = 'quickbooks_not_connected';
    case QuickBooksExpired = 'quickbooks_expired';
    case QuickBooksBusy = 'quickbooks_busy';
    case QboEmployeeNotConfigured = 'qbo_employee_not_configured';
}
