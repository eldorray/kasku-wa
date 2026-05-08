<?php

return [
    'provider' => env('WA_PROVIDER', 'fonnte'),
    'webhook_token' => env('WA_WEBHOOK_TOKEN', 'change-me'),
    'send_real' => env('WA_SEND_REAL', false),

    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
        'send_url' => env('FONNTE_SEND_URL', 'https://api.fonnte.com/send'),
        'device_number' => env('FONNTE_DEVICE_NUMBER'), // contoh: 6281911174403 (tanpa +)
    ],

    'bot' => [
        'name' => 'Kasku Bot',
        'conversation_slug' => 'kasku',
        'greeting' => 'Halo! Aku Kasku Bot 👋 Catat pengeluaran cukup ketik di sini.',
    ],
];
