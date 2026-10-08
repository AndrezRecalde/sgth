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

    // Biométrico (Sirha7). La conexión es `sqlsrv` en database.php.
    'biometrico' => [
        // SENSORID con que se guardan las marcaciones online del SGTH. Por
        // ahora el 2 (decisión de TH, 2026-10-06); los relojes físicos van
        // del 1 al 5 en la tabla Machines.
        'sensor_online' => env('BIOMETRICO_SENSOR_ONLINE', '2'),
        // Desde qué día se registran en Sirha7 los permisos que se aprueban en
        // el SGTH (decisión del 2026-10-07): solo los confirmados desde esa
        // fecha. Los anteriores ya los cargó TH a mano, y aprobarlos cambiaría
        // meses cerrados. Sin fecha, la aprobación en Sirha7 está apagada.
        'aprobacion_permisos_desde' => env('SIRHA7_APROBACION_DESDE'),
    ],

];
