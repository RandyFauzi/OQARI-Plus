<?php

return [
    'enabled' => (bool) env('CUSTOM_DOMAINS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Approval Mode
    |--------------------------------------------------------------------------
    |
    | 'manual' (default for Hostinger): Owner verifies DNS -> status becomes
    | 'pending_approval' -> Admin receives email & SuperAdmin notification ->
    | Admin adds Parked Domain in Hostinger and clicks Approve.
    |
    | 'auto': Automatically activates via Cloudflare for SaaS API.
    |
    */
    'approval_mode' => env('CUSTOM_DOMAIN_APPROVAL_MODE', 'manual'),

    'admin_notification_email' => env('CUSTOM_DOMAIN_ADMIN_EMAIL', 'randyfauzi24@gmail.com'),

    'cname_target' => env('CUSTOM_DOMAIN_CNAME_TARGET', 'oqariplus.appku.site'),

    'origin_ip' => env('CUSTOM_DOMAIN_ORIGIN_IP', '46.202.186.252'),

    'txt_prefix' => '_oqari-verify',

    'cache_ttl_seconds' => 300,

    'system_hosts' => array_values(array_filter(array_unique([
        'localhost',
        '127.0.0.1',
        '::1',
        'appku.site',
        'www.appku.site',
        'oqariplus.appku.site',
        'www.oqariplus.appku.site',
        'cname.oqariplus.appku.site',
        parse_url((string) env('APP_URL', 'http://127.0.0.1'), PHP_URL_HOST),
    ]))),

    'blocked_suffixes' => [
        '.oqariplus.appku.site',
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
