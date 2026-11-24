<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SaaS Billing Master Switch
    |--------------------------------------------------------------------------
    |
    | Keep this false until you are ready to enforce subscription plans and
    | accept payments via Midtrans or Mayar. While false, all billing
    | endpoints return 404 and entitlement checks allow full access.
    |
    */
    'enabled' => (bool) env('SAAS_BILLING_ENABLED', false),

    'default_gateway' => env('SAAS_PAYMENT_GATEWAY', 'midtrans'),

    'trial_days' => (int) env('SAAS_TRIAL_DAYS', 14),

    'grace_days' => (int) env('SAAS_GRACE_DAYS', 3),

    'currency' => 'IDR',

    'gateways' => [
        'midtrans' => [
            'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
            'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            'server_key' => env('MIDTRANS_SERVER_KEY'),
        ],

        'mayar' => [
            'is_production' => (bool) env('MAYAR_IS_PRODUCTION', false),
            'api_key' => env('MAYAR_API_KEY'),
            'webhook_token' => env('MAYAR_WEBHOOK_TOKEN'),
        ],
    ],
];
