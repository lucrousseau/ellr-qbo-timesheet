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
    case QboEmployeeInvalid = 'qbo_employee_invalid';
    case QboEmployeeRemoved = 'qbo_employee_removed';
    case QboEmployeeEmailMissing = 'qbo_employee_email_missing';
    case QboEmployeeEmailConflict = 'qbo_employee_email_conflict';
    case QboInsufficientPermissions = 'qbo_insufficient_permissions';
    case AdminRequired = 'admin_required';
    case EmailNotVerified = 'email_not_verified';
    case OrganizationRequired = 'organization_required';
    case InvalidCredentials = 'invalid_credentials';
}
