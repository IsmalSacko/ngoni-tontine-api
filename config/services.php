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

    // Application mobile Ngoni Tontine : liens d'invitation qui l'ouvrent.
    'tontine_app' => [
        'android_package' => env('TONTINE_ANDROID_PACKAGE', 'com.ismaeldev.fasoya.tontine'),
        // Clé de signature Google Play, puis clé d'importation (builds locaux).
        'android_fingerprints' => array_filter(explode(',', env('TONTINE_ANDROID_FINGERPRINTS',
            '29:61:07:62:77:7D:D9:76:CD:4D:7E:A8:AB:02:3C:27:4C:81:5B:28:91:62:F0:FF:31:72:B0:0A:C1:EF:B7:84,'
            .'C1:13:DB:BA:F9:B3:80:A9:71:4D:FA:88:15:0D:A1:07:78:BD:42:B4:11:A0:9C:1C:BB:14:F6:B8:8B:CD:8A:02'))),
        'ios_app_id' => env('TONTINE_IOS_APP_ID', 'P2VK5LC48H.com.ismaeldev.fasoya.tontine'),
    ],

];
