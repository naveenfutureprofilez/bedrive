<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TUS Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for TUS resumable file upload server
    |
    */

    'enabled' => env('TUS_ENABLED', true),

    'max_size' => env('TUS_MAX_SIZE', 10737418240), // 10GB

    'upload_path' => env('TUS_UPLOAD_PATH', 'storage/app/tus-uploads'),

    'ttl' => env('TUS_TTL', 86400), // 24 hours

    'chunk_size' => env('CHUNK_SIZE', 1048576), // 1MB

    'timeout' => env('UPLOAD_TIMEOUT', 300), // 5 minutes

    'allowed_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',
        'mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm',
        'mp3', 'wav', 'flac', 'aac', 'ogg', 'wma',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'rtf', 'csv', 'json', 'xml',
        'zip', 'rar', '7z', 'tar', 'gz', 'bz2',
        'iso', 'dmg', 'exe', 'msi', 'deb', 'rpm'
    ],

    'cors' => [
        'allow_origin' => ['*'],
        'allow_credentials' => false,
        'allow_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS', 'PATCH'],
        'allow_headers' => ['*'],
        'expose_headers' => ['*'],
    ],
];
