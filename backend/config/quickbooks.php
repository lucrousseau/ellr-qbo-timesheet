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
    | Time activity list scan window
    |--------------------------------------------------------------------------
    |
    | QuickBooks does not support EmployeeRef filters on TimeActivity queries.
    | The API scans paginated rows within a TxnDate window and filters by
    | employee in PHP. Use progressive lookback steps so recent-entry views
    | stop after the first window when possible. Increase caps for large teams.
    |
    */

    'time_activities_lookback_days' => (int) env('QUICKBOOKS_TIME_ACTIVITIES_LOOKBACK_DAYS', 90),

    'time_activities_lookback_steps' => array_values(array_filter(array_map(
        static fn (string $value): int => (int) $value,
        explode(',', (string) env('QUICKBOOKS_TIME_ACTIVITIES_LOOKBACK_STEPS', '14,30,90')),
    ))),

    'time_activities_query_batch_size' => (int) env('QUICKBOOKS_TIME_ACTIVITIES_QUERY_BATCH_SIZE', 100),

    'time_activities_scan_max_pages' => (int) env('QUICKBOOKS_TIME_ACTIVITIES_SCAN_MAX_PAGES', 10),

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

    'list_scan_max_pages' => (int) env('QUICKBOOKS_LIST_SCAN_MAX_PAGES', 10), // @pest-mutate-ignore quickbooks list scan config

    /*
    |--------------------------------------------------------------------------
    | Active timer maximum elapsed seconds
    |--------------------------------------------------------------------------
    |
    | Caps server-side accumulated timer duration to prevent inflated billing.
    |
    */

    'time_tracker_max_accumulated_seconds' => (int) env('QUICKBOOKS_TIME_TRACKER_MAX_ACCUMULATED_SECONDS', 86400),

    /*
    |--------------------------------------------------------------------------
    | Time activity snapshot sync (phase 2 read model)
    |--------------------------------------------------------------------------
    |
    | Webhook verifier token from the Intuit Developer portal. Reconcile runs on
    | a schedule and after OAuth connect to backfill the local snapshot table.
    |
    */

    'webhook_verifier' => env('QUICKBOOKS_WEBHOOK_VERIFIER'),

    'webhook_max_payload_bytes' => (int) env('QUICKBOOKS_WEBHOOK_MAX_PAYLOAD_BYTES', 262_144),

    'webhook_max_notifications' => (int) env('QUICKBOOKS_WEBHOOK_MAX_NOTIFICATIONS', 50),

    'webhook_max_entities_per_notification' => (int) env('QUICKBOOKS_WEBHOOK_MAX_ENTITIES_PER_NOTIFICATION', 100),

    'time_activities_reconcile_enabled' => (bool) env('QUICKBOOKS_TIME_ACTIVITIES_RECONCILE_ENABLED', true),

    'time_activities_reconcile_cron' => env('QUICKBOOKS_TIME_ACTIVITIES_RECONCILE_CRON', '0 * * * *'),

    'time_activities_reconcile_skip_hours' => (int) env('QUICKBOOKS_TIME_ACTIVITIES_RECONCILE_SKIP_HOURS', 1),

    /*
    |--------------------------------------------------------------------------
    | Snapshot retention and bulk purge chunk size
    |--------------------------------------------------------------------------
    |
    | Soft-deleted snapshots are hard-deleted after the retention window by the
    | scheduled prune command. Realm disconnect purges delete rows in chunks to
    | avoid long table locks on large tenants.
    |
    */

    'snapshot_soft_delete_retention_days' => (int) env('QUICKBOOKS_SNAPSHOT_SOFT_DELETE_RETENTION_DAYS', 90),

    'snapshot_purge_chunk_size' => (int) env('QUICKBOOKS_SNAPSHOT_PURGE_CHUNK_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Company timezone for QBO clock-time duration
    |--------------------------------------------------------------------------
    |
    | QuickBooks time activities store StartTime and EndTime as clock values on
    | TxnDate. When EndTime is earlier than StartTime on the same day, QBO treats
    | the interval as crossing midnight. Convert stored UTC timestamps using this
    | timezone when computing durations for snapshots.
    |
    */

    'company_timezone' => env('QUICKBOOKS_COMPANY_TIMEZONE', 'America/Los_Angeles'),

];
