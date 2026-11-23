<?php

return [
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'snap_url' => 'https://app.sandbox.midtrans.com/snap/v1/transactions',
        'snap_script' => 'https://app.sandbox.midtrans.com/snap/snap.js',
    ],
    'demo_password' => env('SSR_DEMO_PASSWORD', 'GalaxyDemo2026!'),
];
