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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'bunny' => [
        'pull_zone'         => env('BUNNY_PULL_ZONE'),
        'token_key'         => env('BUNNY_TOKEN_SECURITY_KEY'),
        'stream_library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
        'stream_api_key'    => env('BUNNY_STREAM_API_KEY'),
        // Storage Zone (raw file storage + CDN delivery)
        'storage_zone'      => env('BUNNY_STORAGE_ZONE'),
        'storage_api_key'   => env('BUNNY_STORAGE_API_KEY'),
        'cdn_url'           => env('BUNNY_CDN_URL'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://localhost:8000') . '/auth/google/callback'),
    ],

    // مزود الرسائل النصية — log للتجربة، يُستبدل بمزود فلسطيني عند التعاقد
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    // Firebase Cloud Messaging — log للتجربة إلى أن يُرفَع ملف اعتماد Service Account
    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    // تحويل شهادات DOCX إلى PDF — على لينكس soffice غالباً بالـ PATH، وعلى ويندوز
    // بالمسار الافتراضي لتثبيت LibreOffice
    'libreoffice' => [
        'binary' => env('LIBREOFFICE_BINARY', PHP_OS_FAMILY === 'Windows'
            ? 'C:\Program Files\LibreOffice\program\soffice.exe'
            : 'soffice'),
    ],

];
