<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MIDTRANS SERVER KEY
    |--------------------------------------------------------------------------
    |
    | Server key dari Midtrans Dashboard.
    | Sandbox: https://dashboard.sandbox.midtrans.com
    | Production: https://dashboard.midtrans.com
    |
    */

    'server_key' => env('MIDTRANS_SERVER_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | MIDTRANS CLIENT KEY
    |--------------------------------------------------------------------------
    |
    | Client key untuk Snap.js di frontend / Android SDK.
    |
    */

    'client_key' => env('MIDTRANS_CLIENT_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | ENVIRONMENT
    |--------------------------------------------------------------------------
    |
    | Set ke true jika sudah production.
    | Default false = sandbox mode.
    |
    */

    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

    /*
    |--------------------------------------------------------------------------
    | SANITIZE
    |--------------------------------------------------------------------------
    |
    | Set ke true agar Midtrans otomatis membersihkan input.
    |
    */

    'is_sanitized' => true,

    /*
    |--------------------------------------------------------------------------
    | 3DS (3D Secure)
    |--------------------------------------------------------------------------
    |
    | Set ke true untuk mengaktifkan 3D Secure pada pembayaran kartu kredit.
    |
    */

    'is_3ds' => true,

];
