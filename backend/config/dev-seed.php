<?php

/**
 * Development seed credentials (local / Docker only).
 *
 * - PLATFORM_*: Ellr operator (manages client organizations).
 * - TENANT_*: sample SaaS client organization (tenant admin).
 */

return [

    'enabled' => filter_var(env('DEV_SEED_ENABLED', false), FILTER_VALIDATE_BOOL),

    'organization_name' => env('DEV_SEED_ORGANIZATION_NAME', 'Ellr Development'),
    'organization_slug' => env('DEV_SEED_ORGANIZATION_SLUG', 'ellr-dev'),

    'tenant_admin_email' => env('DEV_SEED_TENANT_ADMIN_EMAIL', 'admin@ellr.local'),
    'tenant_admin_password' => env('DEV_SEED_TENANT_ADMIN_PASSWORD', 'EllrDev!2026'),
    'tenant_admin_name' => env('DEV_SEED_TENANT_ADMIN_NAME', 'Dev Admin'),

    'platform_enabled' => filter_var(env('DEV_SEED_PLATFORM_ENABLED', true), FILTER_VALIDATE_BOOL),
    'platform_email' => env('DEV_SEED_PLATFORM_EMAIL', 'luc@ellr.ca'),
    'platform_password' => env('DEV_SEED_PLATFORM_PASSWORD', 'EllrDev!2026'),
    'platform_name' => env('DEV_SEED_PLATFORM_NAME', 'Luc Ellr'),

];
