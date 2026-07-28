<?php

/**
 * English API response messages for JSON endpoints.
 */

return [
    'logged_out' => 'Logged out.',
    'registration_disabled' => 'Registration disabled.',
    'invalid_credentials' => 'Invalid credentials.',
    'admin_required' => 'Administrator access required.',
    'email_not_verified' => 'Email address is not verified.',
    'quickbooks_not_connected' => 'QuickBooks is not connected.',
    'quickbooks_expired' => 'QuickBooks connection expired. Please reconnect from the admin app.',
    'quickbooks_busy' => 'QuickBooks is busy. Please retry.',
    'quickbooks_api_error' => 'QuickBooks API error',
    'qbo_employee_not_configured' => 'QBO employee is not configured for this user.',
    'qbo_employee_not_found' => 'QuickBooks employee not found.',
    'qbo_employee_removed' => 'Your QuickBooks employee access was removed. Contact an administrator.',
    'qbo_employee_email_missing' => 'QuickBooks employee has no email address configured.',
    'qbo_employee_email_conflict' => 'QuickBooks employee email is already used by another account.',
    'time_activity_not_found' => 'Time activity not found',
    'password_reset_sent' => 'If that email exists, a reset link has been sent.',
    'password_reset_success' => 'Password reset successfully.',
    'password_changed' => 'Password updated successfully.',
    'email_already_verified' => 'Email already verified.',
    'verification_link_sent' => 'Verification link sent.',
    'time' => [
        'end_after_start' => 'The end time field must be a date after start time.',
        'incomplete_fields' => 'Start and end times are both required when updating time fields.',
    ],
];
