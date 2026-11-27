<?php

return [
    'enabled' => (bool) env('CUSTOM_DOMAINS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | CNAME Target for Tenant Custom Domains (Cloudflare for SaaS)
    |--------------------------------------------------------------------------
    |
    | Tenants point their custom subdomain/domain (e.g. order.tokomerek.com)
    | to this CNAME target. Cloudflare for SaaS handles TLS/SSL termination
    | and forwards traffic to your Hostinger origin.
    |
    */
    'cname_target' => env('CUSTOM_DOMAIN_CNAME_TARGET', 'cname.oqariplus.appku.site'),

    'txt_prefix' => '_oqari-verify',

    'cache_ttl_seconds' => 300,

    /*
    |--------------------------------------------------------------------------
    | Reserved / System Hosts
    |--------------------------------------------------------------------------
    |
    | Hosts that cannot be claimed by tenants and are always treated as main
    | application hosts (never resolved as tenant custom domains).
    |
    */
    'system_hosts' => array_values(array_filter(array_unique([
        'localhost',
        '127.0.0.1',
        '::1',
        'oqariplus.appku.site',
        'www.oqariplus.appku.site',
        'cname.oqariplus.appku.site',
        parse_url((string) env('APP_URL', 'http://127.0.0.1'), PHP_URL_HOST),
    ]))),

    'blocked_suffixes' => [
        '.appku.site',
        '.oqari.test',
        '.localhost',
        '.local',
    ],

    'cloudflare' => [
        'enabled' => (bool) env('CLOUDFLARE_SAAS_ENABLED', false),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
    ],
];
