<?php

/**
 * Add these blocks to your existing config/services.php.
 * (Full file shown for convenience — merge the whatsapp / openai / firebase
 * keys into whatever you already have.)
 */
return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // --- Reservation bot integrations ---------------------------------------

    'openai' => [
        'key'          => env('OPENAI_API_KEY'),
        'chat_model'   => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'audio_model'  => env('OPENAI_AUDIO_MODEL', 'gpt-4o-transcribe'),
        'base_uri'     => env('OPENAI_BASE_URI', 'https://api.openai.com/v1'),
    ],

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),      // test number id in dev
        'waba_id'         => env('WHATSAPP_WABA_ID'),
        'token'           => env('WHATSAPP_TOKEN'),                // permanent system-user token
        'verify_token'    => env('WHATSAPP_VERIFY_TOKEN'),         // webhook GET challenge
        'app_secret'      => env('WHATSAPP_APP_SECRET'),           // X-Hub signature check
        'graph_version'   => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
    ],

    'firebase' => [
        // Admin SDK credentials are read by kreait/laravel-firebase from its own
        // config/firebase.php via the FIREBASE_CREDENTIALS env var — see README.
        'auto_provision' => env('FIREBASE_AUTO_PROVISION', false),

        // Public config consumed by the browser JS SDK (resources/js/firebase.js).
        'web' => [
            'apiKey'            => env('FIREBASE_API_KEY'),
            'authDomain'        => env('FIREBASE_AUTH_DOMAIN'),
            'projectId'         => env('FIREBASE_PROJECT_ID'),
            'appId'             => env('FIREBASE_APP_ID'),
            'messagingSenderId' => env('FIREBASE_MESSAGING_SENDER_ID'),
        ],
    ],

];
