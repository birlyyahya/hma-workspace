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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Custom APIs
    'api_izin' => env('API_IZIN'),
    'api_project' => env('API_PROJECT'),
    'url_project' => env('URL_PROJECT'),
    'api_ca' => env('API_CA'),
    'aws_url' => env('AWS_URL'),
    'aws_url_project' => env('AWS_URL').'workspace',

    'bepm' => [
        'base' => env('API_PROJECT'),
        'token' => env('TOKEN_KEY'),
        'timeout' => (int) env('BEPM_TIMEOUT', 3),
    ],

    'darbe' => [
        'base' => env('API_IZIN'),
        'timeout' => (int) env('DARBE_TIMEOUT', 3),
    ],

    'api_izin_register_endpoint' => env('API_IZIN_REGISTER_ENDPOINT'),

    'whatsapp_gateway' => [
        'base' => env('WHATSAPP_GATEWAY_BASE', 'http://localhost:8080'),
        'timeout' => (int) env('WHATSAPP_GATEWAY_TIMEOUT', 10),
    ],
];
