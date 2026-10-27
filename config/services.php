<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
    ],

    'whatsapp' => [
        'service_url' => env('WA_SERVICE_URL', 'http://localhost:3001'),
        'service_token' => env('WA_SERVICE_TOKEN'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
    ],

    'marketplaces' => [
        'gofood' => [
            'client_id' => env('GOFOOD_CLIENT_ID'),
            'client_secret' => env('GOFOOD_CLIENT_SECRET'),
            'production' => env('GOFOOD_PRODUCTION', false),
        ],
        'shopee_food' => [
            'client_id' => env('SHOPEE_FOOD_CLIENT_ID'),
            'client_secret' => env('SHOPEE_FOOD_CLIENT_SECRET'),
            'production' => env('SHOPEE_FOOD_PRODUCTION', false),
        ],
        'grab_food' => [
            'client_id' => env('GRAB_FOOD_CLIENT_ID'),
            'client_secret' => env('GRAB_FOOD_CLIENT_SECRET'),
            'production' => env('GRAB_FOOD_PRODUCTION', false),
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
