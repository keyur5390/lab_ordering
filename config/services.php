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

    /*
    | Online payment gateways (PROJECT.md §7). The admin chooses the active one in Admin → Settings;
    | a gateway can be chosen once its keys are set. Webhook URL: /webhooks/payments/{stripe|razorpay}.
    */
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'base_url' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com'),
    ],

    /*
    | SMS (PROJECT.md §10). The admin chooses the driver in Admin → Settings; Twilio can be chosen once
    | its keys are set. TWILIO_FROM is a sender number or a Messaging Service SID (MG…).
    */
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
        'base_url' => env('TWILIO_BASE_URL', 'https://api.twilio.com'),
    ],

    /*
    | Maps for addresses and service areas (Leaflet). Defaults to OpenStreetMap's free tiles, which are
    | meant for light use: point MAP_TILE_URL at a tile provider for production traffic.
    */
    'maps' => [
        'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('MAP_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'),
        // TODO(client): centre the maps on the launch city once PROJECT.md §1 names it.
        'default_latitude' => (float) env('MAP_DEFAULT_LATITUDE', 20),
        'default_longitude' => (float) env('MAP_DEFAULT_LONGITUDE', 0),
        'default_zoom' => (int) env('MAP_DEFAULT_ZOOM', 2),
    ],

];
