<?php

return [
    'enabled' => env('OQARI_MCP_ENABLED', false),
    'oauth_enabled' => env('OQARI_MCP_OAUTH_ENABLED', false),
    'codex_client_id' => env('OQARI_MCP_CODEX_CLIENT_ID'),
    'codex_callback_port' => 58951,
    'public_url' => env('OQARI_MCP_PUBLIC_URL'),
    'token_days' => 30,
    'requests_per_minute' => 60,
    'max_connections' => 20,
    'max_page_size' => 50,
    'max_date_range_days' => 90,
    'clients' => [
        'codex' => ['label' => 'Codex', 'icon' => 'fa-terminal'],
        'claude-code' => ['label' => 'Claude Code', 'icon' => 'fa-code'],
        'other' => ['label' => 'Aplikasi lain', 'icon' => 'fa-plug'],
    ],
    'abilities' => [
        'sales' => ['label' => 'Penjualan dan menu terlaris', 'gate' => 'view-reports'],
        'inventory' => ['label' => 'Stok dan transfer', 'gate' => 'manage-inventory'],
        'operations' => ['label' => 'Opname dan pembelian kecil', 'gate' => 'view-inventory-controls'],
        'platform' => ['label' => 'Ringkasan platform OQARI', 'gate' => null],
    ],
];
