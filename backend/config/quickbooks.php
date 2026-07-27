<?php

return [

    /*
  |--------------------------------------------------------------------------
  | QuickBooks Online API
  |--------------------------------------------------------------------------
  |
  | OAuth 2.0 credentials from the Intuit Developer portal.
  | https://developer.intuit.com/
  |
  */

    'client_id' => env('QUICKBOOKS_CLIENT_ID'),
    'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
    'redirect_uri' => env('QUICKBOOKS_REDIRECT_URI', env('APP_URL').'/api/quickbooks/callback'),
    'scope' => env('QUICKBOOKS_SCOPE', 'com.intuit.quickbooks.accounting'),
    'base_url' => env('QUICKBOOKS_BASE_URL', env('APP_ENV') === 'production' ? 'production' : 'development'),

    'frontend_admin_url' => env('FRONTEND_ADMIN_URL', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Expose QuickBooks API error details
    |--------------------------------------------------------------------------
    |
    | When false, 422 responses omit the Intuit response body. Defaults to
    | APP_DEBUG. Set explicitly with QUICKBOOKS_EXPOSE_API_ERRORS.
    |
    */

    'expose_api_errors' => (bool) env('QUICKBOOKS_EXPOSE_API_ERRORS', false),

];
