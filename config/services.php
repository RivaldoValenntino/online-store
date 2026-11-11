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
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'signature_key' => env('MIDTRANS_SIGNATURE_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    ],

    'rajaongkir' => [
        'api_key' => env('RAJAONGKIR_API_KEY'),
        'origin_district_id' => env('RAJAONGKIR_ORIGIN_DISTRICT_ID'),
        'courier' => env('RAJAONGKIR_COURIER', 'jne:sicepat:jnt'),
    ],
    'biteship' => [
        'api_key' => env('BITESHIP_API_KEY'),
        'webhook_header_key' => env('BITESHIP_WEBHOOK_HEADER_KEY', 'X-Biteship-Signature'),
        'webhook_secret' => env('BITESHIP_WEBHOOK_SECRET'),
        'default_couriers' => env('BITESHIP_DEFAULT_COURIERS', 'jne,jnt,sicepat,anteraja'),

        'origin_contact_name' => env('BITESHIP_ORIGIN_CONTACT_NAME'),
        'origin_contact_phone' => env('BITESHIP_ORIGIN_CONTACT_PHONE'),
        'origin_address' => env('BITESHIP_ORIGIN_ADDRESS'),
        'origin_postal_code' => env('BITESHIP_ORIGIN_POSTAL_CODE'),
        'origin_area_id' => env('BITESHIP_ORIGIN_AREA_ID'),
    ],

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

];
