<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | When EquipTrack runs behind a proxy that ends HTTPS for it (Cloudflare,
    | a load balancer), Laravel must trust that proxy's X-Forwarded-* headers
    | to know the visitor used HTTPS and to see their real IP address (login
    | rate limits, the Audit Trail). Laravel's TrustProxies middleware reads
    | this setting.
    |
    | TRUSTED_PROXIES:
    |   (empty)   no proxy: the default, e.g. Laragon or Hostinger on its own
    |   *         trust the proxy in front of the app, e.g. behind Cloudflare
    |   a,b,c     only these proxy IP addresses or ranges
    |
    | Leave it empty when there is no proxy: trusting the headers then would
    | let anyone fake their IP address.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
