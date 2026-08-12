<?php

/**
 * Trusted reverse proxies for TLS termination (SiteGround, CDN, load balancers).
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Consumed by Illuminate\Http\Middleware\TrustProxies. Set TRUSTED_PROXIES=*
    | (or a comma-separated IP/CIDR list) behind SiteGround / a CDN so HTTPS and
    | secure cookies resolve correctly. Leave empty for local or direct HTTPS.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
