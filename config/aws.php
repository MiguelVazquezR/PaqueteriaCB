<?php

use Aws\Laravel\AwsServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | AWS SDK Configuration
    |--------------------------------------------------------------------------
    | ... (documentación sin cambios) ...
    */
    'credentials' => [
        'key'    => env('AWS_ACCESS_KEY_ID', ''),
        'secret' => env('AWS_SECRET_ACCESS_KEY', ''),
    ],
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'), // Corregido de AWS_REGION a AWS_DEFAULT_REGION para coincidir con tu .env
    'version' => 'latest',
    'ua_append' => [
        'L5MOD/' . AwsServiceProvider::VERSION,
    ],

    'rekognition' => [
        'collection_id' => env('AWS_REKOGNITION_COLLECTION_ID', ''),
    ],
];
