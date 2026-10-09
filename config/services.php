<?php

/**
 * Reservation bot service config. (AUDIT FIX) The `firebase.web` block now reads
 * the VITE_FIREBASE_* env vars that the project's .env actually defines — the
 * previous FIREBASE_* names were empty, so the browser Firebase config was null
 * and sign-in couldn't initialize.
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
        'key'         => env('OPENAI_API_KEY'),
        'chat_model'  => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'audio_model' => env('OPENAI_AUDIO_MODEL', 'gpt-4o-transcribe'),
        'base_uri'    => env('OPENAI_BASE_URI', 'https://api.openai.com/v1'),
    ],

    'whatsapp' => [
        'phone_number_id'   => env('WHATSAPP_PHONE_NUMBER_ID'),
        'waba_id'           => env('WHATSAPP_WABA_ID'),
        'token'             => env('WHATSAPP_TOKEN'),
        'verify_token'      => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret'        => env('WHATSAPP_APP_SECRET'),
        'graph_version'     => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
        'reminder_template' => env('WHATSAPP_REMINDER_TEMPLATE', 'reminder_same_day'),
    ],

    'firebase' => [
        // Admin SDK credentials are read by kreait/laravel-firebase via FIREBASE_CREDENTIALS.
        'auto_provision' => env('FIREBASE_AUTO_PROVISION', false),

        // Public config for the browser JS SDK. Reads the VITE_FIREBASE_* vars
        // the .env defines (falling back to non-VITE names if you ever set those).
        'web' => [
            'apiKey'            => env('VITE_FIREBASE_API_KEY', env('FIREBASE_API_KEY')),
            'authDomain'        => env('VITE_FIREBASE_AUTH_DOMAIN', env('FIREBASE_AUTH_DOMAIN')),
            'projectId'         => env('VITE_FIREBASE_PROJECT_ID', env('FIREBASE_PROJECT_ID')),
            'storageBucket'     => env('VITE_FIREBASE_STORAGE_BUCKET'),
            'appId'             => env('VITE_FIREBASE_APP_ID', env('FIREBASE_APP_ID')),
            'messagingSenderId' => env('VITE_FIREBASE_MESSAGING_SENDER_ID', env('FIREBASE_MESSAGING_SENDER_ID')),
        ],
    ],

];
