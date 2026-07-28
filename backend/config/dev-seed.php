<?php

/**
 * Development seed credentials (local / Docker only).
 */

return [

    'enabled' => filter_var(env('DEV_SEED_ENABLED', false), FILTER_VALIDATE_BOOL),

    'admin_email' => env('DEV_SEED_ADMIN_EMAIL', 'admin@ellr.local'),
    'admin_password' => env('DEV_SEED_ADMIN_PASSWORD', 'password'),
    'admin_name' => env('DEV_SEED_ADMIN_NAME', 'Dev Admin'),

    'user_email' => env('DEV_SEED_USER_EMAIL', 'timesheet@ellr.local'),
    'user_password' => env('DEV_SEED_USER_PASSWORD', 'password'),
    'user_name' => env('DEV_SEED_USER_NAME', 'Dev Timesheet'),

];
