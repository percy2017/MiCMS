<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ChatBot Module Configuration
    |--------------------------------------------------------------------------
    |
    | Credenciales globales para los canales del módulo ChatBot.
    | Las credenciales se leen de .env (no se guardan por canal en BD).
    |
    */

    'openwa' => [
        'base_url' => env('OPENWA_BASE_URL', 'https://openwa.example.com/api'),
        'api_key' => env('OPENWA_API_KEY', ''),
        'webhook_secret' => env('OPENWA_WEBHOOK_SECRET', ''),
        'timeout' => (int) env('OPENWA_TIMEOUT', 15),
    ],

    'evolution' => [
        'server_url' => env('EVOLUTION_DEFAULT_SERVER_URL', 'https://evolution.example.com'),
        'api_key' => env('EVOLUTION_DEFAULT_API_KEY', ''),
    ],

    'media_disk' => env('CHATBOT_MEDIA_DISK', 'public'),
];
