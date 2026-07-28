<?php

/**
 * QuickBooks OAuth credentials, scopes, frontend redirect URLs, and error exposure.
 */

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

    /*
    |--------------------------------------------------------------------------
    | Time activity list cap
    |--------------------------------------------------------------------------
    |
    | Maximum rows per QuickBooks query when listing time activities. Clients may
    | request fewer via max_results and paginate with start_position.
    |
    */

    'time_activities_max_results' => (int) env('QUICKBOOKS_TIME_ACTIVITIES_MAX_RESULTS', 100),

    /*
    |--------------------------------------------------------------------------
    | Time activity truncated probe
    |--------------------------------------------------------------------------
    |
    | When true, list responses probe QuickBooks for one more row to set
    | meta.truncated accurately. When false, truncated is true whenever the
    | page is full (faster, but may over-report additional pages).
    |
    */

    'time_activities_probe_truncated' => (bool) env('QUICKBOOKS_TIME_ACTIVITIES_PROBE_TRUNCATED', true),

    /*
    |--------------------------------------------------------------------------
    | QBO list endpoints (employees, customers, projects, services, ...)
    |--------------------------------------------------------------------------
    |
    | Shared cache TTL and MAXRESULTS cap for read-heavy picker lists. Set TTL to
    | 0 to disable caching. Use ?refresh=1 on list endpoints to bypass cache.
    |
    */

    'list_cache_ttl_minutes' => (int) env(
        'QUICKBOOKS_LIST_CACHE_TTL_MINUTES',
        env('QUICKBOOKS_EMPLOYEES_CACHE_TTL_MINUTES', 15),
    ),

    'list_max_results' => (int) env(
        'QUICKBOOKS_LIST_MAX_RESULTS',
        env('QUICKBOOKS_EMPLOYEES_MAX_RESULTS', 1000),
    ),

];
