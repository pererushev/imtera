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

    'yandex_parser' => [
        'node_path' => env('YANDEX_PARSER_NODE_PATH', 'node'),
        'timeout' => (int) env('YANDEX_PARSER_TIMEOUT', 600),
        'http_fallback' => (bool) env('YANDEX_PARSER_HTTP_FALLBACK', false),
        'aspects' => (bool) env('YANDEX_PARSER_ASPECTS', false),
        'aspect_limit' => (int) env('YANDEX_PARSER_ASPECT_LIMIT', 15),
        'scroll_rounds' => (int) env('YANDEX_PARSER_SCROLL_ROUNDS', 60),
        'scroll_delay_ms' => (int) env('YANDEX_PARSER_SCROLL_DELAY_MS', 500),
        'scroll_settle_ms' => (int) env('YANDEX_PARSER_SCROLL_SETTLE_MS', 800),
        'stale_limit' => (int) env('YANDEX_PARSER_STALE_LIMIT', 10),
    ],

];
